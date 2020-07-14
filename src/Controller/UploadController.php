<?php
/**
 * 02.05.2020.
 */

declare(strict_types=1);

namespace App\Controller;

use App\Service\{Exception\HandlerNotFoundException, FileChunk, FileReceiverInterface};
use League\Flysystem\FileNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{File\UploadedFile, JsonResponse, Request};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Upload Controller.
 *
 * @Route(name="upload_file", path="/upload")
 */
class UploadController extends AbstractController
{
    public const UPLOADED_FIELD = 'upload';
    public const HANDLER_NAME_FIELD = 'type';

    private FileReceiverInterface $fileReceiver;

    public function __construct(FileReceiverInterface $fileReceiver)
    {
        $this->fileReceiver = $fileReceiver;
    }

    /**
     * @param Request         $request
     * @param LoggerInterface $logger
     *
     * @return JsonResponse
     */
    public function __invoke(Request $request, LoggerInterface $logger): JsonResponse
    {
        $file = $request->files->get(self::UPLOADED_FIELD);
        if (!$file instanceof UploadedFile) {
            throw new BadRequestHttpException(\sprintf('Upload request must contains file in \'%s\' field', self::UPLOADED_FIELD));
        }
        $handlerName = $request->get(self::HANDLER_NAME_FIELD);
        if ($handlerName === null) {
            throw new BadRequestHttpException(\sprintf('You should declare the handler name in request \'%s\' field', self::HANDLER_NAME_FIELD));
        }
        try {
            $handler = $this->fileReceiver->getHandler($handlerName);
        } catch (HandlerNotFoundException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
        $fileChunk = FileChunk::create($request, $file);
        if ($fileChunk->getNumber() === 0) {
            $filesystem = $handler->getFilesystem();
            $tempFile = \uuid_create();

            if (
                $filesystem->put($tempFile, 'Test file. Should be deleted.') === false &&
                !$filesystem->has($tempFile)
            ) {
                throw new HttpException(523, 'Unable to store file');
            }
            try {
                $filesystem->delete($tempFile);
            } catch (FileNotFoundException $e) {
                $logger->error('Unable to delete temporary file', [
                    'file' => $tempFile
                ]);
            }
        }
        $handler->setChunk($fileChunk)->storeChunk();

        return new JsonResponse([
            'done' => $handler->getPercents(),
            'file' => $handler->getFullFile(),
            'url' => $handler->getFileUrl(),
        ]);
    }
}
