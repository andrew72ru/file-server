<?php
/**
 * 04.05.2020.
 */

declare(strict_types=1);

namespace App\Controller\FileAccess;

use League\Flysystem\FilesystemInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract class AbstractFileAccessController extends AbstractController
{
    /**
     * @var FilesystemInterface[]
     */
    protected array $filesystems;

    /**
     * @param FilesystemInterface[] $filesystems
     */
    public function __construct(array $filesystems)
    {
        $this->filesystems = $filesystems;
    }

    /**
     * @param string $type
     *
     * @return FilesystemInterface
     */
    protected function getFs(string $type): FilesystemInterface
    {
        $fs = $this->filesystems[$type] ?? null;
        if ($fs === null) {
            throw new BadRequestHttpException(\sprintf('No \'%s\' type registered', $type));
        }

        return $fs;
    }
}
