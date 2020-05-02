<?php
/**
 * User: andrew
 * Date: 2018-10-12
 * Time: 16:31.
 */

declare(strict_types=1);

namespace Andrew72ru\ChunkUploadBundle\Handler;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class UploadHandler.
 */
class UploadHandler extends AbstractHandler
{
    const CHUNK_SIZE = '_chunkSize';

    const CURRENT_CHUNK_SIZE = '_currentChunkSize';

    const CHUNK_NUMBER = '_chunkNumber';

    const TOTAL_SIZE = '_totalSize';

    const UNIQUE_ID = '_uniqueId';

    /**
     * @param Request $request
     *
     * @return bool
     */
    public static function canBeUsedWithRequest(Request $request): bool
    {
        return $request->get(self::CHUNK_SIZE, false)
            && $request->get(self::CURRENT_CHUNK_SIZE, false)
            && $request->get(self::CHUNK_NUMBER, false)
            && $request->get(self::TOTAL_SIZE, false)
            && $request->get(self::UNIQUE_ID, false);
    }

    /**
     * @return bool
     */
    public function isChunked(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function storeChunk(): bool
    {
        $file = $this->getFile();

        try {
            $dest = fopen($this->getTargetFilePath(), 'ab');
            $incoming = fopen($file->getPathname(), 'rb');
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
        while ($buffer = fread($incoming, 4096)) {
            fwrite($dest, $buffer);
        }
        fclose($incoming);
        fclose($dest);

        return true;
    }

    /**
     * @return int
     */
    protected function getTotalSize(): int
    {
        return (int) $this->getRequest()->get(self::TOTAL_SIZE);
    }

    /**
     * @return int
     */
    protected function getTargetSize(): int
    {
        return filesize($this->getTargetFilePath());
    }

    /**
     * @return string
     */
    protected function getTargetFilePath(): string
    {
        $name = $this->getRequest()->get(self::UNIQUE_ID);

        return sys_get_temp_dir() . '/' . $name . '.' . $this->getFile()->guessExtension() ?: $this->getFile()->getExtension();
    }

    /**
     * @return bool
     */
    public function isFinished(): bool
    {
        return $this->getTargetSize() >= $this->getTotalSize();
    }

    /**
     * @return int
     */
    public function getPercents()
    {
        $float = $this->getTargetSize() / $this->getTotalSize();

        return floor($float * 100);
    }

    /**
     * @return File
     */
    public function getFullFile(): UploadedFile
    {
        return new UploadedFile($this->getTargetFilePath(), $this->getFile()->getClientOriginalName(), $this->getFile()->getMimeType());
    }
}
