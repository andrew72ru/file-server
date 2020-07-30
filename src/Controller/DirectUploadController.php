<?php declare(strict_types=1);
/**
 * 01.06.2020.
 */

namespace App\Controller;

use App\Service\Exception\HandlerNotFoundException;
use App\Service\FileChunk;
use App\Service\FileReceiverInterface;
use App\Service\Handler\ImageHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Direct upload controller.
 *
 * @Route(name="direct_upload_file", path="/direct-upload")
 */
class DirectUploadController extends AbstractController
{
    public const TARGET_PATH_PARAM = 'path';

    /**
     * @var FileReceiverInterface
     */
    private FileReceiverInterface $fileReceiver;

    public function __construct(FileReceiverInterface $fileReceiver)
    {
        $this->fileReceiver = $fileReceiver;
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function __invoke(Request $request): Response
    {
        $file = $request->files->get(UploadController::UPLOADED_FIELD);
        if (!$file instanceof UploadedFile) {
            throw new BadRequestHttpException(\sprintf('Upload request must contains file in \'%s\' field', UploadController::UPLOADED_FIELD));
        }
        if (!$file->isValid()) {
            throw new BadRequestHttpException($file->getErrorMessage());
        }

        $handlerName = $request->get(UploadController::HANDLER_NAME_FIELD);
        if ($handlerName === null) {
            throw new BadRequestHttpException(\sprintf('You should declare the handler name in request \'%s\' field', UploadController::HANDLER_NAME_FIELD));
        }
        try {
            $fs = $this->fileReceiver->getHandler($handlerName)->getFilesystem();
        } catch (HandlerNotFoundException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        $slugger = new AsciiSlugger();
        $remoteHost = $request->server->get('REMOTE_HOST', '');
        $path = $slugger->slug($remoteHost)->toString();
        if (($tp = $request->get(FileChunk::TARGET_PATH)) !== null) {
            $path = \sprintf('%s/%s', $path, \ltrim($tp, '/'));
        }
        $targetName = \sprintf('%s/%s.%s', $path, \uuid_create(), $file->guessExtension());

        if (!$fs->put($targetName, \file_get_contents($file->getPathname()))) {
            throw new BadRequestHttpException('Unable to store file');
        }

        return $this->redirectToRoute('download_file', [
            'type' => $handlerName === ImageHandler::NAME ? 'images' : $handlerName,
            'filename' => $targetName,
        ]);
    }
}
