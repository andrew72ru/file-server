<?php
/**
 * 03.05.2020.
 */

declare(strict_types=1);

namespace App\Tests\Store;

use App\Controller\UploadController;
use App\Service\FileChunk;
use App\Tests\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ControllerTest extends KernelTestCase
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

    protected function tearDown(): void
    {
        parent::tearDown();
        $dir = \sprintf('%s/pieces', $this->getDataDir());
        if (\is_dir($dir)) {
            $files = new \FilesystemIterator($dir);
            foreach ($files as $file) {
                \unlink($file->getPathname());
            }

            \rmdir($dir);
        }
    }

    protected function request(string $uuid, int $num): Request
    {
        $dir = \sprintf('%s/pieces', $this->getDataDir());
        if (!\is_dir($dir) && !\mkdir($dir) && !\is_dir($dir)) {
            throw new \RuntimeException(\sprintf('%s not created', $dir));
        }

        $from = self::CHUNK_SIZE * $num;

        $this->file->rewind();
        $this->file->fseek($from);

        $piece = $this->file->fread(self::CHUNK_SIZE);

        $filename = \sprintf('%s/%d.pdf', $dir, $num);
        \file_put_contents($filename, $piece);

        $request = Request::create('/upload');
        $parameters = new ParameterBag([
            FileChunk::CHUNK_SIZE => self::CHUNK_SIZE,
            FileChunk::CURRENT_CHUNK_SIZE => '',
            FileChunk::CHUNK_NUMBER => $num,
            FileChunk::TOTAL_SIZE => $this->size,
            FileChunk::UNIQUE_ID => $uuid,
            UploadController::HANDLER_NAME_FIELD => 'image',
        ]);
        $request->request = $parameters;
        $request->files->set(UploadController::UPLOADED_FIELD, new UploadedFile($filename, (string) $num, null, null, true));

        return $request;
    }

    public function testChunkUpload(): void
    {
        self::bootKernel();
        $controller = self::$container->get(UploadController::class);
        $uuid = \uuid_create();
        $fs = self::$container->get('oneup_flysystem.image.filesystem_filesystem');
        $handler = self::$container->get('file_handler.image');
        $handler->setUrlPrefix('https://google.com/images/');

        foreach (\range(0, ($this->count - 1)) as $item) {
            $request = $this->request($uuid, $item);
            /** @var JsonResponse $response */
            $response = $controller($request);
            $content = \json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

            if (($content['file'] ?? null) !== null) {
                $this->assertTrue($fs->has($content['file']));
                $this->assertEquals($content['url'], \sprintf('https://google.com/images/%s', $content['file']));

                $file = $fs->read($content['file']);
                $origin = \file_get_contents($this->getDataDir('deserialization_tutorial6.pdf'));
                $this->assertEquals($file, $origin);
            }
        }
    }

    public function testRequestWithoutFile(): void
    {
        $this->expectException(BadRequestHttpException::class);

        self::bootKernel();
        $controller = self::$container->get(UploadController::class);

        $request = Request::create('/upload');
        $controller($request);
    }

    public function testRequestWithoutHandlerName(): void
    {
        $this->expectException(BadRequestHttpException::class);

        self::bootKernel();
        $controller = self::$container->get(UploadController::class);

        $request = Request::create('/upload');
        $request->files->set(UploadController::UPLOADED_FIELD, new UploadedFile($this->getDataDir('deserialization_tutorial6.pdf'), 'deserialization_tutorial6.pdf', null, null, true));

        $controller($request);
    }

    public function testRequestWithWrongHandlerName(): void
    {
        $this->expectException(BadRequestHttpException::class);

        self::bootKernel();
        $controller = self::$container->get(UploadController::class);
        $request = Request::create('/upload');
        $request->files->set(UploadController::UPLOADED_FIELD, new UploadedFile($this->getDataDir('deserialization_tutorial6.pdf'), 'deserialization_tutorial6.pdf', null, null, true));
        $request->request->set(UploadController::HANDLER_NAME_FIELD, 'wrong_handler_name');

        $controller($request);
    }
}
