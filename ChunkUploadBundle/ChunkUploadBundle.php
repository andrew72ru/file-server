<?php
/**
 * User: andrew
 * Date: 2018-10-12
 * Time: 09:04.
 */

declare(strict_types=1);

namespace Andrew72ru\ChunkUploadBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class ChunkUploadBundle.
 * For handle chunk-uploaded files.
 */
class ChunkUploadBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
    }
}
