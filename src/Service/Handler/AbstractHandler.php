<?php declare(strict_types=1);

namespace App\Service\Handler;

use App\Service\Exception\FileHandlerException;
use App\Service\Exception\InvalidCallException;
use App\Service\FileChunkInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Mime\MimeTypes;

/**
 * AbstractHandler.
 * Common methods for handlers.
 */
abstract class AbstractHandler implements HandlerInterface
{
    protected ?FileChunkInterface $chunk = null;
    protected FilesystemOperator $filesystem;
    protected ?string $urlPrefix = null;

    /**
     * {@inheritDoc}
     */
    public function setUrlPrefix(string $prefix = null): HandlerInterface
    {
        $this->urlPrefix = $prefix;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setChunk(FileChunkInterface $chunk): HandlerInterface
    {
        $this->chunk = $chunk;

        return $this;
    }

    public function storeChunk(): bool
    {
        $this->validate();
        $file = $this->getFile();

        $dirName = $this->tempDirName();
        if (!\is_dir($dirName) && (!\mkdir($dirName) && !\is_dir($dirName))) {
            throw new FileHandlerException(\sprintf('Directory "%s" was not created', $dirName));
        }

        $destName = $this->dirName($dirName, $this->getChunk()->getNumber());
        $dst = \fopen($destName, 'ab');
        $inc = \fopen($file->getRealPath(), 'rb');
        if (!\is_file($file->getRealPath())) {
            throw new FileHandlerException(\sprintf('File %s not a file', $file->getRealPath()));
        }
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

    /**
     * Merge result file.
     */
    protected function merge(): void
    {
        $this->validate();

        $dirName = $this->tempDirName();
        $files = \iterator_to_array(new \FilesystemIterator($dirName));
        $files = \array_filter($files, static fn ($element) => $element instanceof \SplFileInfo);
        \uasort($files, static fn (\SplFileInfo $a, \SplFileInfo $b): int => ((int) $a->getBasename() < (int) $b->getBasename()) ? -1 : 1);

        $dstPath = $this->dirName(\sys_get_temp_dir(), $this->getFilename());
        $dst = \fopen($dstPath, 'ab');

        foreach ($files as $file) {
            $src = \fopen($file->getRealPath(), 'rb');

            \fwrite($dst, \fread($src, $file->getSize()));
            \fclose($src);

            \unlink($file->getRealPath());
        }
        \fclose($dst);

        $dstRead = \fopen($dstPath, 'rb');
        $targetName = $this->dirName(\rtrim(($this->getTargetPath() ?? ''), '/'), $this->getFilename());
        $this->filesystem->writeStream($targetName, $dstRead);

        \fclose($dstRead);
    }

    protected function getTargetPath(): ?string
    {
        if ($this->chunk === null) {
            return null;
        }

        return $this->chunk->getTargetPath();
    }

    protected function tempDirName(): string
    {
        return $this->dirName(\sys_get_temp_dir(), $this->getChunk()->getUniqueId());
    }

    public function isFinished(): bool
    {
        $this->validate();

        return ($this->getChunk()->getNumber() + 1) >= $this->getChunk()->getChunksCount();
    }

    public function getPercents(): int
    {
        $this->validate();
        $value = $this->getChunk()->getNumber() / $this->getChunk()->getChunksCount();

        return (int) \ceil($value * 100);
    }

    public function getFullFile(): ?string
    {
        if ($this->isFinished()) {
            return $this->getFilename();
        }

        return null;
    }

    public function getFileUrl(): ?string
    {
        if (($filename = $this->getFullFile()) === null) {
            return null;
        }

        if (($tp = $this->getChunk()->getTargetPath()) !== null) {
            $filename = $this->dirName(\rtrim($tp, '/'), $filename);
        }

        if ($this->urlPrefix !== null) {
            return $this->dirName(\rtrim($this->urlPrefix, '/'), $filename);
        }

        return null;
    }

    /**
     * Filename with extension.
     */
    protected function getFilename(): string
    {
        $this->validate();
        $file = $this->getFile();
        $mime = \method_exists($file, 'getClientMimeType')
            ? $file->getClientMimeType() : $file->getMimeType();

        $extension = 'bin';
        $ex = MimeTypes::getDefault()->getExtensions($mime ?? 'application/octet-stream');
        if (!empty($ex)) {
            $extension = $ex[0];
        }

        return \sprintf('%s.%s', $this->getChunk()->getUniqueId(), $extension);
    }

    /**
     * Uploaded file.
     */
    protected function getFile(): File
    {
        return $this->getChunk()->getFile();
    }

    protected function getChunk(): FileChunkInterface
    {
        if (($existingChunk = $this->chunk) === null) {
            throw new InvalidCallException(\sprintf('You must set \'%s\' instance to handler first', FileChunkInterface::class));
        }

        return $existingChunk;
    }

    /**
     * Validate Chunk object.
     */
    protected function validate(): void
    {
        if ($this->chunk === null) {
            throw new InvalidCallException(\sprintf('You must set \'%s\' instance to handler first', FileChunkInterface::class));
        }
    }

    public function getFilesystem(): FilesystemOperator
    {
        return $this->filesystem;
    }

    private function dirName(string $path, string | int $name): string
    {
        return \sprintf('%s/%s', $path, $name);
    }
}
