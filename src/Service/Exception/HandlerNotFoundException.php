<?php
/**
 * 02.05.2020.
 */

declare(strict_types=1);

namespace App\Service\Exception;

class HandlerNotFoundException extends \RuntimeException
{
    public function __construct($handlerName = '', $message = '', $code = 0, \Throwable $previous = null)
    {
        if (empty($message) && !empty($handlerName)) {
            $message = \sprintf('Handler with name \'%s\' not declared', $handlerName);
        }

        parent::__construct($message, $code, $previous);
    }
}
