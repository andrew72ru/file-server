<?php
/**
 * User: andrew
 * Date: 2018-10-12
 * Time: 17:25.
 */

declare(strict_types=1);

namespace Andrew72ru\ChunkUploadBundle\Handler;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class AbstractHandler.
 */
abstract class AbstractHandler implements UploadHandlerInterface
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var UploadedFile
     */
    private $file;

    /**
     * AbstractHandler constructor.
     *
     * @param Request      $request
     * @param UploadedFile $file
     */
    public function __construct(Request $request, UploadedFile $file)
    {
        $this->request = $request;
        $this->file = $file;
    }

    /**
     * @return bool
     */
    public function isChunked(): bool
    {
        return false;
    }

    /**
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @return UploadedFile
     */
    public function getFile(): UploadedFile
    {
        return $this->file;
    }

    /**
     * @return bool
     */
    public function storeChunk(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isFinished(): bool
    {
        return true;
    }

    /**
     * @return int
     */
    public function getPercents()
    {
        return 100;
    }

    /**
     * @return UploadedFile
     */
    public function getFullFile(): UploadedFile
    {
        return $this->file;
    }
}
