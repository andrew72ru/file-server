<?php
/**
 * 02.05.2020.
 */

declare(strict_types=1);

namespace App\Service\Handler;

use App\Service\FileChunk;
use League\Flysystem\FilesystemOperator;

interface HandlerInterface
{
    public function setChunk(FileChunk $chunk): self;

    public function getName(): string;

    public function storeChunk(): bool;

    public function isFinished(): bool;

    public function getPercents(): int;

    public function getFullFile(): ?string;

    public function getFileUrl(): ?string;

    /**
     * @return $this
     */
    public function setUrlPrefix(string $prefix = null): self;

    public function getFilesystem(): FilesystemOperator;
}
