<?php
/**
 * 04.05.2020.
 */

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\File;

interface FileChunkInterface
{
    public function getTargetPath(): ?string;

    /**
     * Chunk size.
     */
    public function getSize(): int;

    /**
     * Current chunk size.
     */
    public function getCurrentSize(): int;

    /**
     * Number of chunk.
     */
    public function getNumber(): int;

    /**
     * Total file size.
     */
    public function getTotalSize(): int;

    /**
     * Unique ID for file.
     */
    public function getUniqueId(): string;

    public function getFile(): File;

    public function getChunksCount(): int;
}
