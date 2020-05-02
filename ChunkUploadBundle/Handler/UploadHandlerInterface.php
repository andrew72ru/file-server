<?php
/**
 * User: andrew
 * Date: 2018-10-12
 * Time: 16:31.
 */

namespace Andrew72ru\ChunkUploadBundle\Handler;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

interface UploadHandlerInterface
{
    /**
     * @param Request $request
     *
     * @return bool
     */
    public static function canBeUsedWithRequest(Request $request): bool;

    /**
     * @return bool
     */
    public function isChunked(): bool;

    /**
     * @return bool
     */
    public function storeChunk(): bool;

    /**
     * @return bool
     */
    public function isFinished(): bool;

    /**
     * @return int
     */
    public function getPercents(): int;

    /**
     * @return File
     */
    public function getFullFile(): UploadedFile;
}
