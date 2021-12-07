<?php declare(strict_types=1);

namespace App\Service;

use App\Service\Exception\HandlerNotFoundException;
use App\Service\Handler\HandlerInterface;

class FileReceiver implements FileReceiverInterface
{
    /**
     * @param HandlerInterface[] $fileHandlers
     */
    public function __construct(private array $fileHandlers)
    {
    }

    public function getHandler(string $name): HandlerInterface
    {
        $fileHandler = $this->fileHandlers[$name] ?? null;
        if (!$fileHandler instanceof HandlerInterface) {
            throw new HandlerNotFoundException($name);
        }

        return $fileHandler;
    }
}
