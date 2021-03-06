<?php
/**
 * 02.05.2020.
 */

declare(strict_types=1);

namespace App\Service\Handler;

use League\Flysystem\FilesystemOperator;

/**
 * Handler for video.
 */
final class VideoHandler extends AbstractHandler
{
    public const NAME = 'video';

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
