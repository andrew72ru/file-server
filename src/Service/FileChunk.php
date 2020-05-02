<?php
/**
 * 02.05.2020
 */

declare(strict_types=1);


namespace App\Service;


use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class FileChunk
{
    public const CHUNK_SIZE = '_chunkSize';
    public const CURRENT_CHUNK_SIZE = '_currentChunkSize';
    public const CHUNK_NUMBER = '_chunkNumber';
    public const TOTAL_SIZE = '_totalSize';
    public const UNIQUE_ID = '_uniqueId';

    private int $size;
    private int $currentSize;
    private int $number;
    private int $totalSize;
    private string $uniqueId;
    private ?File $file;

    /**
     * Factory method
     *
     * @param Request $request
     * @param File    $file
     * @return self
     */
    public static function create(Request $request, File $file): self
    {
        $keys = [
            self::CHUNK_SIZE,
            self::CURRENT_CHUNK_SIZE,
            self::CHUNK_NUMBER,
            self::TOTAL_SIZE,
            self::UNIQUE_ID,
        ];
        foreach ($keys as $key) {
            if ($request->get($key) === null) {
                throw new BadRequestHttpException(\sprintf('Request field \'%s\' must be defined', $key));
            }
        }

        return new self(
            (int) $request->get(self::CHUNK_SIZE),
            (int) $request->get(self::CURRENT_CHUNK_SIZE),
            (int) $request->get(self::CHUNK_NUMBER),
            (int) $request->get(self::TOTAL_SIZE),
            (string) $request->get(self::UNIQUE_ID),
            $file,
        );
    }

    /**
     * FileChunk constructor.
     * @param int $size
     * @param int $currentSize
     * @param int $number
     * @param int $totalSize
     * @param string $uniqueId
     * @param File|null $file
     */
    public function __construct(int $size, int $currentSize, int $number, int $totalSize, string $uniqueId, File $file = null)
    {
        $this->size = $size;
        $this->currentSize = $currentSize;
        $this->number = $number;
        $this->totalSize = $totalSize;
        $this->uniqueId = $uniqueId;
        $this->file = $file;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @return int
     */
    public function getCurrentSize(): int
    {
        return $this->currentSize;
    }

    /**
     * @return int
     */
    public function getNumber(): int
    {
        return $this->number;
    }

    /**
     * @return int
     */
    public function getTotalSize(): int
    {
        return $this->totalSize;
    }

    /**
     * @return string
     */
    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }

    /**
     * @param File|null $file
     * @return FileChunk
     */
    public function setFile(?File $file): FileChunk
    {
        $this->file = $file;
        return $this;
    }

    /**
     * @return File|null
     */
    public function getFile(): ?File
    {
        return $this->file;
    }
}
