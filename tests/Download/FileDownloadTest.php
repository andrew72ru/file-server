<?php
/**
 * 04.05.2020.
 */

declare(strict_types=1);

namespace App\Tests\Download;

use App\Controller\DownloadController;
use App\Tests\KernelTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        $response = $controller('images', 'deserialization_tutorial6.pdf');

        $this->assertNotEmpty($response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('deserialization_tutorial6.pdf', $response->headers->get('Content-Disposition'));
        $this->assertEquals(\filesize($path), $response->headers->get('Content-Length'));
    }

    public function testTryToDownloadWithWrongType(): void
    {
        $this->expectException(BadRequestHttpException::class);

        self::bootKernel();
        $controller = self::$container->get(DownloadController::class);
        $controller('not-registered-type', 'any-file');
    }

    public function testTryToDownloadNotExistingFile(): void
    {
        $this->expectException(NotFoundHttpException::class);
        self::bootKernel();
        $controller = self::$container->get(DownloadController::class);
        $controller('images', 'not-existing-file.pdf');
    }
}
