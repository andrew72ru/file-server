<?php
/**
 * 02.05.2020.
 */

declare(strict_types=1);

namespace App\Tests\FileReceiver;

use App\Service\Exception\HandlerNotFoundException;
use App\Service\Exception\InvalidCallException;
use App\Service\FileReceiver;
use App\Service\FileReceiverInterface;
use App\Service\Handler\ImageHandler;
use App\Service\Handler\VideoHandler;
use App\Tests\KernelTestCase;

class FileReceiverTest extends KernelTestCase
{
    public function testLoadedHandlers(): void
    {
        self::bootKernel();
        $service = self::$container->get(FileReceiverInterface::class);
        $this->assertInstanceOf(FileReceiver::class, $service);

        $this->assertInstanceOf(ImageHandler::class, $service->getHandler(ImageHandler::NAME));
        $this->assertInstanceOf(VideoHandler::class, $service->getHandler(VideoHandler::NAME));
    }

    public function testNotExistsHandler(): void
    {
        $this->expectException(HandlerNotFoundException::class);
        self::bootKernel();
        $service = self::$container->get(FileReceiverInterface::class);
        $service->getHandler('non_existent-handler-name');
    }

    public function testExceptionWithNoChunkInHandler(): void
    {
        $this->expectException(InvalidCallException::class);

        self::bootKernel();
        $service = self::$container->get(FileReceiverInterface::class);
        $imageHandler = $service->getHandler(ImageHandler::NAME);
        $imageHandler->getPercents();
    }
}
