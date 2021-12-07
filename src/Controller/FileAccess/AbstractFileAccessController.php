<?php declare(strict_types=1);

namespace App\Controller\FileAccess;

use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract class AbstractFileAccessController extends AbstractController
{
    /**
     * @var FilesystemOperator[]
     */
    protected array $filesystems;

    /**
     * @param FilesystemOperator[] $filesystems
     */
    public function __construct(array $filesystems)
    {
        $this->filesystems = $filesystems;
    }

    protected function getFs(string $type): FilesystemOperator
    {
        $fs = $this->filesystems[$type] ?? null;
        if ($fs === null) {
            throw new BadRequestHttpException(\sprintf('No \'%s\' type registered', $type));
        }

        return $fs;
    }
}
