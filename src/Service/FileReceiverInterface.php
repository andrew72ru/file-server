<?php
/**
 * 02.05.2020.
 */

declare(strict_types=1);

namespace App\Service;

use App\Service\Handler\HandlerInterface;

interface FileReceiverInterface
{
    public function getHandler(string $name): HandlerInterface;
}
