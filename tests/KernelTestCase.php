<?php
/**
 * 02.05.2020.
 */

declare(strict_types=1);

namespace App\Tests;

class KernelTestCase extends \Symfony\Bundle\FrameworkBundle\Test\KernelTestCase
{
    public function getDataDir(string $append = null): string
    {
        $path = __DIR__ . '/data';
        if ($append !== null) {
            $path = \sprintf('%s/%s', $path, $append);
        }

        if (!\is_readable($path)) {
            throw new \RuntimeException(\sprintf('File \'%s\' not found', $path));
        }

        return $path;
    }
}
