<?php
/**
 * User: andrew
 * Date: 2018-10-12
 * Time: 17:09.
 */

declare(strict_types=1);

namespace Andrew72ru\ChunkUploadBundle\Handler;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class HandlerFactory
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var array
     */
    protected static $handlers = [
        UploadHandler::class,
    ];

    /**
     * HandlerFactory constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param Request $request
     *
     * @return string
     */
    public static function classFromRequest(Request $request): string
    {
        foreach (self::$handlers as $handler) {
            if ($handler::canBeUsedWithRequest($request)) {
                return $handler;
            }
        }

        return DefaultHandler::class;
    }
}
