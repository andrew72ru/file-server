<?php
/**
 * 02.05.2020.
 */

declare(strict_types=1);

namespace App\Tests\Store;

use App\Service\FileChunk;
use App\Service\FileReceiverInterface;
use App\Service\Handler\HandlerInterface;
use App\Service\Handler\ImageHandler;
use App\Tests\KernelTestCase;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class StoreImageFileTest extends KernelTestCase
{
    private const CHUNK_SIZE = 1024 * 16;

    private \SplFileObject $file;
    private int $size;
    private int $count;

    protected function setUp(): void
    {
        $this->file = new \SplFileObject($this->getDataDir('deserialization_tutorial6.pdf'), 'rb');
        $this->size = $this->file->getSize();
        $this->count = (int) \ceil($this->size / self::CHUNK_SIZE);
    }

    protected function getRequest(string $uuid, int $num = 1): Request
    {
        if ($num > $this->count) {
            throw new \RuntimeException('Number larger then count');
        }

        $request = Request::create('/upload');
        $parameters = new ParameterBag([
            FileChunk::CHUNK_SIZE => self::CHUNK_SIZE,
            FileChunk::CURRENT_CHUNK_SIZE => self::CHUNK_SIZE,
            FileChunk::CHUNK_NUMBER => $num,
            FileChunk::TOTAL_SIZE => $this->size,
            FileChunk::UNIQUE_ID => $uuid,
        ]);
        $request->request = $parameters;
        $request->files->set('upload', new UploadedFile($this->getDataDir('deserialization_tutorial6.pdf'), 'deserialization_tutorial6.pdf', 'application/pdf', null, true));

        return $request;
    }

    public function testStoreFile(): void
    {
        self::bootKernel();

        $uuid = \uuid_create();

        $request = $this->getRequest($uuid, 1);
        $service = static::getContainer()->get(FileReceiverInterface::class);

        /** @var HandlerInterface $handler */
        $handler = $service->getHandler(ImageHandler::NAME);
        $handler->setChunk(FileChunk::create($request, $request->files->get('upload')));
        $this->assertTrue($handler->storeChunk());

        $this->assertFalse($handler->isFinished());
        $this->assertIsInt($handler->getPercents());

        $tempPath = \sprintf('%s/%s/%d', \sys_get_temp_dir(), $uuid, 1);
        $this->assertFileExists($tempPath);
    }

    public function testMergeFiles(): void
    {
        self::bootKernel();

        $uuid = \uuid_create();
        $dir = \sprintf('%s/%s', \sys_get_temp_dir(), $uuid);
        if (!\is_dir($dir) && !\mkdir($dir) && !\is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Directory %s was not created', $dir));
        }

        for ($i = 1; $i <= $this->count; ++$i) {
            $part = $this->file->fread(self::CHUNK_SIZE);
            $dst = \fopen(\sprintf('%s/%d', $dir, $i), 'wb');
            \fwrite($dst, $part, self::CHUNK_SIZE);
            \fclose($dst);
        }

        /** @var FilesystemOperator $fs */
        $fs = static::getContainer()->get('oneup_flysystem.image.filesystem_filesystem');
        $handler = new ImageHandler($fs);
        $request = $this->getRequest($uuid, $this->count);
        $handler->setChunk(FileChunk::create($request, $request->files->get('upload')));

        $merge = (new \ReflectionObject($handler))->getMethod('merge');
        $merge->setAccessible(true);
        $merge->invoke($handler);

        $this->assertTrue($fs->fileExists(\sprintf('%s.pdf', $uuid)));

        $file = $fs->read(\sprintf('%s.pdf', $uuid));
        $origin = \file_get_contents($this->getDataDir('deserialization_tutorial6.pdf'));

        $this->assertEquals($file, $origin);
    }
}
