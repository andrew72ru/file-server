<?php
/**
 * 03.05.2020.
 */

declare(strict_types=1);

namespace App\Tests\Store;

use App\Controller\UploadController;
use App\Service\FileChunk;
use App\Service\FileReceiverInterface;
use App\Service\Handler\HandlerInterface;
use App\Tests\KernelTestCase;
use League\Flysystem\Filesystem;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        /** @var UploadController $controller */
        $controller = self::$container->get(UploadController::class);
        /** @var LoggerInterface $logger */
        $logger = self::$container->get(LoggerInterface::class);
        $uuid = \uuid_create();
        $fs = self::$container->get('oneup_flysystem.image.filesystem_filesystem');
        $handler = self::$container->get('file_handler.image');
        $handler->setUrlPrefix('https://google.com/images/');

        foreach (\range(0, ($this->count - 1)) as $item) {
            $request = $this->request($uuid, $item);
            $response = $controller($request, $logger);
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
        /** @var UploadController $controller */
        $controller = self::$container->get(UploadController::class);
        /** @var LoggerInterface $logger */
        $logger = self::$container->get(LoggerInterface::class);

        $request = Request::create('/upload');
        $controller($request, $logger);
    }

    public function testRequestWithoutHandlerName(): void
    {
        $this->expectException(BadRequestHttpException::class);

        self::bootKernel();
        /** @var UploadController $controller */
        $controller = self::$container->get(UploadController::class);
        /** @var LoggerInterface $logger */
        $logger = self::$container->get(LoggerInterface::class);

        $request = Request::create('/upload');
        $request->files->set(UploadController::UPLOADED_FIELD, new UploadedFile($this->getDataDir('deserialization_tutorial6.pdf'), 'deserialization_tutorial6.pdf', null, null, true));

        $controller($request, $logger);
    }

    public function testRequestWithWrongHandlerName(): void
    {
        $this->expectException(BadRequestHttpException::class);

        self::bootKernel();
        /** @var UploadController $controller */
        $controller = self::$container->get(UploadController::class);
        /** @var LoggerInterface $logger */
        $logger = self::$container->get(LoggerInterface::class);
        $request = Request::create('/upload');
        $request->files->set(UploadController::UPLOADED_FIELD, new UploadedFile($this->getDataDir('deserialization_tutorial6.pdf'), 'deserialization_tutorial6.pdf', null, null, true));
        $request->request->set(UploadController::HANDLER_NAME_FIELD, 'wrong_handler_name');

        $controller($request, $logger);
    }

    public function testNotAvailableToWrite(): void
    {
        self::bootKernel();
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('put')->willReturn(false);

        $handler = $this->createMock(HandlerInterface::class);
        $handler->method('getFilesystem')->willReturn($filesystem);

        $receiver = $this->getMockBuilder(FileReceiverInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $receiver->expects(self::once())
            ->method('getHandler')
            ->willReturn($handler);

        /** @var FileReceiverInterface $receiver */
        $controller = new UploadController($receiver);
        /** @var LoggerInterface $logger */
        $logger = self::$container->get(LoggerInterface::class);
        $uuid = \uuid_create();
        $request = $this->request($uuid, 0);

        $this->expectException(HttpException::class);
        $controller($request, $logger);

        $this->expectExceptionMessageMatches('Unable to store file');
    }
}
