<?php declare(strict_types=1);
/**
 * 14.05.2020.
 */

namespace App\Tests\Remove;

use App\Controller\FileAccess\DeleteController;
use App\Tests\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        /* @var Response $response */
        $controller($request, 'images', $this->filename);
        $this->assertStringContainsString('not found', $this->getExpectedExceptionMessage());
    }
}
