<?php
/**
 * User: andrew
 * Date: 2018-10-12
 * Time: 17:12.
 */

declare(strict_types=1);

namespace Andrew72ru\ChunkUploadBundle\Handler;

use Symfony\Component\HttpFoundation\Request;

class DefaultHandler extends AbstractHandler
{
    /**
     * @param Request $request
     *
     * @return bool
     */
    public static function canBeUsedWithRequest(Request $request): bool
    {
        return true;
    }
}
