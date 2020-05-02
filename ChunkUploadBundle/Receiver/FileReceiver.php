<?php
/**
 * User: andrew
 * Date: 2018-10-12
 * Time: 17:05.
 */

declare(strict_types=1);

namespace Andrew72ru\ChunkUploadBundle\Receiver;

use Andrew72ru\ChunkUploadBundle\Handler\AbstractHandler;
use Andrew72ru\ChunkUploadBundle\Handler\UploadHandlerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

class FileReceiver
{
    /**
     * @var UploadedFile|null
     */
    private $file;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var UploadHandlerInterface|AbstractHandler
     */
    private $handler;

    /**
     * FileReceiver constructor.
     *
     * @param string  $field
     * @param Request $request
     * @param string  $uploadHandler
     */
    public function __construct(string $field, Request $request, string $uploadHandler)
    {
        $this->request = $request;
        $this->file = $request->files->get($field);

        $this->handler = new $uploadHandler($request, $this->file);
    }

    /**
     * @return UploadHandlerInterface
     */
    public function getHandler(): UploadHandlerInterface
    {
        return $this->handler;
    }
}
