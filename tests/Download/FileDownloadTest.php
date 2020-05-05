<?php
/**
 * 04.05.2020.
 */

declare(strict_types=1);

namespace App\Tests\Download;

use App\Controller\FileAccess\DownloadController;
use App\Tests\KernelTestCase;
use Symfony\Component\HttpFoundation\{Request, Response, StreamedResponse};
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, NotFoundHttpException};

class FileDownloadTest extends KernelTestCase
{
    public function testDownloadFile(): void
    {
        self::bootKernel();

        $path = $this->getDataDir('deserialization_tutorial6.pdf');
        $imageFs = self::$container->get('oneup_flysystem.image.filesystem_filesystem');
        $imageFs->put('deserialization_tutorial6.pdf', \file_get_contents($path));

        $controller = self::$container->get(DownloadController::class);
        /** @var Response $response */
        $response = $controller(Request::create('/download'), 'images', 'deserialization_tutorial6.pdf');

        $this->assertNotEmpty($response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('deserialization_tutorial6.pdf', $response->headers->get('Content-Disposition'));
        $this->assertEquals(\filesize($path), $response->headers->get('Content-Length'));
    }

    public function testDownloadImageFile(): void
    {
        self::bootKernel();

        $path = $this->getDataDir('IMG_0144.jpg');
        $imageFs = self::$container->get('oneup_flysystem.image.filesystem_filesystem');
        $imageFs->put('IMG_0144.jpg', \file_get_contents($path));

        $controller = self::$container->get(DownloadController::class);
        /** @var StreamedResponse $response */
        $response = $controller(Request::create('/download'), 'images', 'IMG_0144.jpg');

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertNotEmpty($response->headers->get('Content-Range'));
        $this->assertNotEmpty($response->headers->get('Accept-Ranges'));
    }

    public function testTryToDownloadWithWrongType(): void
    {
        $this->expectException(BadRequestHttpException::class);

        self::bootKernel();
        $controller = self::$container->get(DownloadController::class);
        $controller(Request::create('/download'), 'not-registered-type', 'any-file');
    }

    public function testTryToDownloadNotExistingFile(): void
    {
        $this->expectException(NotFoundHttpException::class);
        self::bootKernel();
        $controller = self::$container->get(DownloadController::class);
        $controller(Request::create('/download'), 'images', 'not-existing-file.pdf');
    }
}
