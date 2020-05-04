<?php
/**
 * 04.05.2020.
 */

declare(strict_types=1);

namespace App\Controller\FileAccess;

use League\Flysystem\FileNotFoundException;
use Symfony\Component\HttpFoundation\{HeaderUtils, Response};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Download as file.
 *
 * @Route(name="download_file", path="/download/{type}/{filename}")
 */
class DownloadController extends AbstractFileAccessController
{
    /**
     * @param string $type
     * @param string $filename
     *
     * @return Response
     */
    public function __invoke(string $type, string $filename): Response
    {
        $fs = $this->getFs($type);

        try {
            $fileContent = $fs->read($filename);
            $fileSize = $fs->getSize($filename);
        } catch (FileNotFoundException $e) {
            throw new NotFoundHttpException(\sprintf('File \'%s\' not found', $filename));
        }

        $response = new Response($fileContent);
        $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename);
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('Content-Length', $fileSize);

        return $response;
    }
}
