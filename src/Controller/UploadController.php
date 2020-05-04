<?php
/**
 * 02.05.2020.
 */

declare(strict_types=1);

namespace App\Controller;

use App\Service\{Exception\HandlerNotFoundException, FileChunk, FileReceiverInterface};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{File\UploadedFile, JsonResponse, Request};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
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
        $handler->setChunk($fileChunk)->storeChunk();

        return new JsonResponse([
            'done' => $handler->getPercents(),
            'file' => $handler->getFullFile(),
        ]);
    }
}
