<?php
/**
 * 02.05.2020.
 */

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * File Chunk representation.
 * Contains all parameters needed to store a file as chunk (part).
 */
final class FileChunk implements FileChunkInterface
{
    public const CHUNK_SIZE = '_chunkSize';
    public const CURRENT_CHUNK_SIZE = '_currentChunkSize';
    public const CHUNK_NUMBER = '_chunkNumber';
    public const TOTAL_SIZE = '_totalSize';
    public const UNIQUE_ID = '_uniqueId';
    public const TARGET_PATH = '_targetPath';

    private int $size;
    private int $currentSize;
    private int $number;
    private int $totalSize;
    private string $uniqueId;
    private ?string $targetPath = null;
    private File $file;

    /**
     * Factory method.
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

        $result = new self(
            (int) $request->get(self::CHUNK_SIZE),
            (int) $request->get(self::CURRENT_CHUNK_SIZE),
            (int) $request->get(self::CHUNK_NUMBER),
            (int) $request->get(self::TOTAL_SIZE),
            (string) $request->get(self::UNIQUE_ID),
            $file,
        );
        $path = (new AsciiSlugger())->slug($request->server->get('REMOTE_HOST', ''))->toString();

        if (($tp = $request->get(self::TARGET_PATH, null)) !== null) {
            $path = \sprintf('%s/%s', $path, \ltrim($tp, '/'));
        }

        if (!empty($path)) {
            $result->setTargetPath($path);
        }

        return $result;
    }

    /**
     * FileChunk constructor.
     */
    private function __construct(int $size, int $currentSize, int $number, int $totalSize, string $uniqueId, File $file)
    {
        $this->size = $size;
        $this->currentSize = $currentSize;
        $this->number = $number;
        $this->totalSize = $totalSize;
        $this->uniqueId = $uniqueId;
        $this->file = $file;
    }

    public function setTargetPath(?string $targetPath): self
    {
        $this->targetPath = $targetPath;

        return $this;
    }

    public function getTargetPath(): ?string
    {
        return $this->targetPath;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getCurrentSize(): int
    {
        return $this->currentSize;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function getTotalSize(): int
    {
        return $this->totalSize;
    }

    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }

    public function getFile(): File
    {
        return $this->file;
    }

    public function getChunksCount(): int
    {
        return (int) \ceil($this->totalSize / $this->size);
    }
}
