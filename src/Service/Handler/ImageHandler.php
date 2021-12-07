<?php
/**
 * 02.05.2020.
 */

declare(strict_types=1);

namespace App\Service\Handler;

use League\Flysystem\FilesystemOperator;

/**
 * Handler for images.
 */
final class ImageHandler extends AbstractHandler
{
    public const NAME = 'image';

    public function __construct(FilesystemOperator $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return self::NAME;
    }
}
