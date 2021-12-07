<?php
/**
 * 02.05.2020.
 */

declare(strict_types=1);

namespace App\Service;

use App\Service\Exception\HandlerNotFoundException;
use App\Service\Handler\HandlerInterface;

/**
 * Wrapper for handlers.
 */
class FileReceiver implements FileReceiverInterface
{
    /**
     * @var HandlerInterface[]
     */
    private array $fileHandlers;

    /**
     * FileReceiver constructor.
     *
     * @param HandlerInterface[] $fileHandlers
     */
    public function __construct(array $fileHandlers)
    {
        $this->fileHandlers = $fileHandlers;
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
