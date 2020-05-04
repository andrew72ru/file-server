<?php
/**
 * 04.05.2020.
 */

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\File;

interface FileChunkInterface
{
    /**
     * Chunk size.
     *
     * @return int
     */
    public function getSize(): int;

    /**
     * Current chunk size.
     *
     * @return int
     */
    public function getCurrentSize(): int;

    /**
     * Number of chunk.
     *
     * @return int
     */
    public function getNumber(): int;

    /**
     * Total file size.
     *
     * @return int
     */
    public function getTotalSize(): int;

    /**
     * Unique ID for file.
     *
     * @return string
     */
    public function getUniqueId(): string;

    /**
     * @return File
     */
    public function getFile(): File;

    /**
     * @return int
     */
    public function getChunksCount(): int;
}
