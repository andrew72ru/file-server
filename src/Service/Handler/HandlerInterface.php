<?php
/**
 * 02.05.2020
 */

declare(strict_types=1);


namespace App\Service\Handler;

use App\Service\FileChunk;

interface HandlerInterface
{
    /**
     * @param FileChunk $chunk
     * @return self
     */
    public function setChunk(FileChunk $chunk): self;

    /**
     * @return string
     */
    public function getName(): string;

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
     * @return string|null
     */
    public function getFullFile(): ?string;
}
