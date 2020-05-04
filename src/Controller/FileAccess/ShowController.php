<?php
/**
 * 04.05.2020.
 */

declare(strict_types=1);

namespace App\Controller\FileAccess;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Show image file.
 *
 * @Route(name="show_file", path="/show/images/{filename}")
 */
class ShowController extends AbstractFileAccessController
{
    public function __invoke(Request $request, string $filename)
    {
        return $this->showImage($filename, $this->getFs('images'));
    }

    protected function showImage(string $filename, FilesystemInterface $fs): Response
    {
        try {
            $response = new Response($fs->read($filename), Response::HTTP_OK, [
                'Content-Length' => $fs->getSize($filename),
                'Content-Type' => $fs->getMimetype($filename),
            ]);
        } catch (FileNotFoundException $e) {
            throw new NotFoundHttpException(\sprintf('File \'%s\' not found', $filename));
        }

        return $response;
    }
}
