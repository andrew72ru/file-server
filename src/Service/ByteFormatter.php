<?php
/**
 * 05.05.2020.
 */

declare(strict_types=1);

namespace App\Service;

class ByteFormatter
{
    public static function format(int $size, int $precision = 2): string
    {
        if ($size === 0) {
            return '0';
        }

        $base = \log($size, 1024);
        $suffixes = ['', 'K', 'M', 'G', 'T'];
        $suffixKey = \floor($base);
        $suffix = $suffixes[$suffixKey] ?? '';

        return \sprintf('%s%s', \round(1024 ** ($base - \floor($base)), $precision), $suffix);
    }
}
