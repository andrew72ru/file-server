<?php declare(strict_types=1);
/**
 * 14.05.2020.
 */

namespace App\Tests\Remove;

use App\Controller\FileAccess\DeleteController;
use App\Tests\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RemoveFileTest extends KernelTestCase
{
    private string $filename;
    private string $path;
    /**
     * @var \League\Flysystem\Filesystem|object|null
     */
    private $fs;

    protected function setUp(): void
    {
        parent::setUp();
        if (!self::$container instanceof ContainerInterface) {
            self::bootKernel();
        }

        $this->filename = 'IMG_0144.jpg';
        $this->path = $this->getDataDir($this->filename);
        $this->fs = self::$container->get('oneup_flysystem.image.filesystem_filesystem');
    }

    public function testNormalFileDeletion(): void
    {
        $this->fs->put($this->filename, \file_get_contents($this->path));
        $controller = self::$container->get(DeleteController::class);
        $request = Request::create('/del', 'DELETE');
        $request->headers->set(DeleteController::SECURITY_HEADER, ($_ENV['SECURITY_HEADER_SECRET'] ?? $_SERVER['SECURITY_HEADER_SECRET']));

        /** @var Response $response */
        $response = $controller($request, 'images', $this->filename);

        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertFalse($this->fs->has($this->filename));
    }

    public function testFileNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $controller = self::$container->get(DeleteController::class);
        $request = Request::create('/del', 'DELETE');
        $request->headers->set(DeleteController::SECURITY_HEADER, ($_ENV['SECURITY_HEADER_SECRET'] ?? $_SERVER['SECURITY_HEADER_SECRET']));

        /* @var Response $response */
        $controller($request, 'images', $this->filename);
        $this->assertStringContainsString('not found', $this->getExpectedExceptionMessage());
    }

    public function testAccessDeniedWithNoHeader(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $controller = self::$container->get(DeleteController::class);
        $request = Request::create('/del', 'DELETE');
        $controller($request, 'images', $this->filename);

        $this->assertStringContainsString('No security header provided', $this->getExpectedExceptionMessage());
    }

    public function testAccessDeniedOnWrongToken(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $controller = self::$container->get(DeleteController::class);
        $request = Request::create('/del', 'DELETE');
        $request->headers->set(DeleteController::SECURITY_HEADER, 'not-valid-header');
        $controller($request, 'images', $this->filename);

        $this->assertStringContainsString('Wrong security header', $this->getExpectedExceptionMessage());
    }
}
