<?php
/**
 * 02.05.2020
 */

declare(strict_types=1);


namespace App\Service\Handler;


use App\Service\Exception\InvalidCallException;
use App\Service\FileChunk;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

/**
 * AbstractHandler.
 * Common methods for handlers.
 */
abstract class AbstractHandler implements HandlerInterface
{
    protected ?FileChunk $chunk;
    protected Filesystem $filesystem;

    /**
     * @inheritDoc
     */
    public function setChunk(FileChunk $chunk): HandlerInterface
    {
        $this->chunk = $chunk;

        return $this;

    }

    /**
     * @inheritDoc
     */
    public function storeChunk(): bool
    {
        $this->validate();
        $file = $this->getFile();

        $handle = \fopen($file->getRealPath(), 'rb');
        $result = $this->filesystem->putStream($this->getFilename(), $handle);
        \fclose($handle);

        return $result;
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     */
    public function isFinished(): bool
    {
        $this->validate();

        return $this->filesystem->getSize($this->getFilename()) >= $this->chunk->getTotalSize();
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     */
    public function getPercents(): int
    {
        $this->validate();
        $value = $this->filesystem->getSize($this->getFilename()) / $this->chunk->getTotalSize();

        return (int) \floor($value * 100);
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     */
    public function getFullFile(): ?string
    {
        if ($this->isFinished()) {
            return $this->getFilename();
        }

        return null;
    }

    /**
     * Filename with extension.
     *
     * @return string
     */
    protected function getFilename(): string
    {
        $this->validate();
        return sprintf('%s.%s', $this->chunk->getUniqueId(), $this->getFile()->guessExtension() ?: $this->getFile()->getExtension());
    }

    /**
     * Uploaded file.
     *
     * @return File
     */
    protected function getFile(): File
    {
        if (($file = $this->chunk->getFile()) === null) {
            throw new InvalidCallException(\sprintf('File is not set to chunk %s', $this->chunk->getUniqueId()));
        }

        return $file;
    }

    /**
     * Validate Chunk object.
     */
    protected function validate(): void
    {
        if ($this->chunk === null) {
            throw new InvalidCallException(\sprintf('You must set \'%s\' instance to handler first', FileChunk::class));
        }
        if ($this->chunk->getFile() === null) {
            throw new InvalidCallException(\sprintf('File is not set to chunk %s', $this->chunk->getUniqueId()));
        }
    }
}
