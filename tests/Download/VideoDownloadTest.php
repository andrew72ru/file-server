<?php

declare(strict_types=1);
/**
 * 14.05.2020.
 */

namespace App\Tests\Download;

use App\Controller\FileAccess\DownloadController;
use App\Tests\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoDownloadTest extends KernelTestCase
{
    private string $filename;
    private string $path;

    /**
     * @var \League\Flysystem\Filesystem|object|null
     */
    private $videoFs;

    protected function setUp(): void
    {
        parent::setUp();
        if (!self::$container instanceof ContainerInterface) {
            self::bootKernel();
        }

        $this->filename = '530262769.mp4';
        $this->path = $this->getDataDir($this->filename);
        $this->videoFs = self::$container->get('oneup_flysystem.video.filesystem_filesystem');
    }

    public function testNormalVideoDownload(): void
    {
        $this->videoFs->put($this->filename, \file_get_contents($this->path));

        $controller = self::$container->get(DownloadController::class);
        /** @var Response $response */
        $response = $controller(Request::create('/download'), 'video', $this->filename);
        $this->assertNotEmpty($response->headers->get('Cache-Control'));
        $this->assertNotEmpty($response->headers->get('Content-Range'));
        $this->assertEquals(\filesize($this->path), $response->headers->get('Content-Length'));
    }

    public function testRangedVideoDownload(): void
    {
        $this->videoFs->put($this->filename, \file_get_contents($this->path));
        $controller = self::$container->get(DownloadController::class);

        $request = Request::create('/download');
        $request->headers->set('HTTP_RANGE', 'bytes=0-'); // 3145728

        /** @var Response $response */
        $response = $controller($request, 'video', $this->filename);
        $this->assertNotEmpty($response->headers->get('Content-Range'));
        $this->assertNotEmpty($response->headers->get('Accept-Ranges'));
        $this->assertFalse($response->isEmpty());
        $this->assertEquals(Response::HTTP_PARTIAL_CONTENT, $response->getStatusCode());
    }

    public function testMakeVideoStreamIsNotResource(): void
    {
        $this->expectException(\RuntimeException::class);

        $controller = self::$container->get(DownloadController::class);
        $makeVideoStream = (new \ReflectionObject($controller))->getMethod('makeVideoStream');
        $makeVideoStream->setAccessible(true);

        $request = Request::create('/download', 'GET', [], [], [], ['HTTP_RANGE' => 'bytes=0-']);
        $response = new StreamedResponse();
        $makeVideoStream->invokeArgs($controller, [$request, 3145728, (object) [], $response]);
        $this->assertStringContainsString('needs a resource as first argument', $this->getExpectedExceptionMessage());
    }

    public function testRangeNotSatisfiableInMakeVideoStream(): void
    {
        $controller = self::$container->get(DownloadController::class);
        $makeVideoStream = (new \ReflectionObject($controller))->getMethod('makeVideoStream');
        $makeVideoStream->setAccessible(true);
        $request = Request::create('/download', 'GET', [], [], [], ['HTTP_RANGE' => 'bytes=0,3145728']);
        $response = new StreamedResponse();

        $makeVideoStream->invokeArgs($controller, [$request, 3145728, \fopen($this->path, 'rb'), $response]);
        $this->assertEquals(Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE, $response->getStatusCode());
    }

    public function testWrongRangeRequestedInMakeVideoStream(): void
    {
        $controller = self::$container->get(DownloadController::class);
        $makeVideoStream = (new \ReflectionObject($controller))->getMethod('makeVideoStream');
        $makeVideoStream->setAccessible(true);
        $request = Request::create('/download', 'GET', [], [], [], ['HTTP_RANGE' => 'bytes=3145728-10']);
        $response = new StreamedResponse();

        $makeVideoStream->invokeArgs($controller, [$request, 3145728, \fopen($this->path, 'rb'), $response]);
        $this->assertEquals(Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE, $response->getStatusCode());
    }

    public function testMakeStreamMethod(): void
    {
        $image = $this->getDataDir('IMG_0144.jpg');
        $this->expectOutputString(\file_get_contents($image));

        $controller = self::$container->get(DownloadController::class);
        $makeStream = (new \ReflectionObject($controller))->getMethod('makeStream');
        $makeStream->setAccessible(true);

        $makeStream->invokeArgs($controller, [\fopen($image, 'rb'), 0, \filesize($image)]);
    }
}
