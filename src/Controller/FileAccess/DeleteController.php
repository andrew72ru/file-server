<?php declare(strict_types=1);
/**
 * 14.05.2020.
 */

namespace App\Controller\FileAccess;

use League\Flysystem\FileNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\{
    HttpFoundation\Request,
    HttpFoundation\Response,
    HttpKernel\Exception\NotFoundHttpException,
    Routing\Annotation\Route
};

/**
 * Remove file.
 *
 * @Route(name="delete_file", path="/delete/{type}/{filename}", methods={"DELETE"})
 */
class DeleteController extends AbstractFileAccessController
{
    private LoggerInterface $logger;

    /**
     * @param array           $filesystems
     * @param LoggerInterface $logger
     */
    public function __construct(array $filesystems, LoggerInterface $logger)
    {
        parent::__construct($filesystems);
        $this->logger = $logger;
    }

    /**
     * @param Request $request
     * @param string  $type
     * @param string  $filename
     *
     * @return Response
     */
    public function __invoke(Request $request, string $type, string $filename)
    {
        $this->logger->info(\sprintf('Try to delete \'%s\' file from \'%s\' category', $filename, $type));
        $fs = $this->getFs($type);

        try {
            $fs->delete($filename);
            $this->logger->info(\sprintf('File \'%s\' was deleted from \'%s\'', $filename, $type), \array_merge($request->headers->all(), $request->server->all()));

            return new Response(null, Response::HTTP_NO_CONTENT);
        } catch (FileNotFoundException $e) {
            throw new NotFoundHttpException(\sprintf('File \'%s\' not found in \'%s\'', $filename, $type));
        }
    }
}
