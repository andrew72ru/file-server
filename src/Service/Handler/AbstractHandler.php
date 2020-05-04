<?php
/**
 * 02.05.2020.
 */

declare(strict_types=1);

namespace App\Service\Handler;

use App\Service\Exception\InvalidCallException;
use App\Service\FileChunk;
use League\Flysystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

/**
 * AbstractHandler.
 * Common methods for handlers.
 */
abstract class AbstractHandler implements HandlerInterface
{
    protected ?FileChunk $chunk = null;
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

        $dirName = $this->tempDirName();
        if (!\is_dir($dirName) && !\mkdir($dirName) && !\is_dir($dirName)) {
            throw new \RuntimeException(\sprintf('Directory "%s" was not created', $dirName));
        }

        $destName = \sprintf('%s/%s', $dirName, $this->chunk->getNumber());
        $dst = \fopen($destName, 'ab');
        $inc = \fopen($file->getRealPath(), 'rb');
        while ($b = \fread($inc, 4096)) {
            \fwrite($dst, $b);
        }
        \fclose($inc);
        \fclose($dst);

        if ($this->isFinished()) {
            $this->merge();
        }

        return true;
    }

    protected function merge(): void
    {
        $this->validate();

        $dirName = $this->tempDirName();
        $files = \iterator_to_array(new \FilesystemIterator($dirName));
        \uasort($files, fn (\SplFileInfo $a, \SplFileInfo $b):int => ((int) $a->getBasename() < (int) $b->getBasename()) ? -1 : 1);

        $dstPath = \sprintf('%s/%s', \sys_get_temp_dir(), $this->getFilename());
        $dst = \fopen($dstPath, 'ab');

        foreach ($files as $file) {
            $src = \fopen($file->getRealPath(), 'rb');

            \fwrite($dst, \fread($src, $file->getSize()));
            \fclose($src);

            \unlink($file->getRealPath());
        }
        \fclose($dst);

        $dstRead = \fopen($dstPath, 'rb');
        $this->filesystem->putStream($this->getFilename(), $dstRead);

        \fclose($dstRead);
    }

    protected function tempDirName(): string
    {
        return \sprintf('%s/%s', \sys_get_temp_dir(), $this->chunk->getUniqueId());
    }

    /**
     * @inheritDoc
     */
    public function isFinished(): bool
    {
        $this->validate();

        return ($this->chunk->getNumber() + 1) >= $this->chunk->getChunksCount();
    }

    /**
     * @inheritDoc
     */
    public function getPercents(): int
    {
        $this->validate();
        $value = $this->chunk->getNumber() / $this->chunk->getChunksCount();

        return (int) \ceil($value * 100);
    }

    /**
     * @inheritDoc
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

        return sprintf('%s.%s', $this->chunk->getUniqueId(), $this->getFile()->guessExtension() ?? $this->getFile()->getExtension());
    }

    /**
     * Uploaded file.
     *
     * @return File
     */
    protected function getFile(): File
    {
        return $this->chunk->getFile();
    }

    /**
     * Validate Chunk object.
     */
    protected function validate(): void
    {
        if ($this->chunk === null) {
            throw new InvalidCallException(\sprintf('You must set \'%s\' instance to handler first', FileChunk::class));
        }
    }
}
