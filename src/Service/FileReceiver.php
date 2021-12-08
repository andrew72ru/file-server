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
        foreach ($this->fileHandlers as $fileHandler) {
            if ($fileHandler instanceof HandlerInterface && $fileHandler->getName() === $name) {
                return $fileHandler;
            }
        }

        throw new HandlerNotFoundException($name);
    }
}
