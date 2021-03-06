<?php
/**
 * 02.05.2020.
 */

declare(strict_types=1);

namespace App\Tests\FileChunk;

use App\Service\FileChunk;
use App\Tests\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class FileChunkFactoryTest extends KernelTestCase
{
    private function getFile(): UploadedFile
    {
        return new UploadedFile(
            $this->getDataDir('deserialization_tutorial6.pdf'),
            'deserialization_tutorial6.pdf',
            'application/pdf',
            null,
            true,
        );
    }

    public function testNormalCreation(): void
    {
        $request = Request::create('/upload');
        $uuid = \uuid_create();
        $parameters = new ParameterBag([
            FileChunk::CHUNK_SIZE => 150,
            FileChunk::CURRENT_CHUNK_SIZE => 100,
            FileChunk::CHUNK_NUMBER => 1,
            FileChunk::TOTAL_SIZE => 65535,
            FileChunk::UNIQUE_ID => $uuid,
        ]);
        $request->request = $parameters;
        $file = $this->getFile();

        $chunk = FileChunk::create($request, $file);
        $this->assertSame($chunk->getSize(), $parameters->get(FileChunk::CHUNK_SIZE));
        $this->assertSame($chunk->getCurrentSize(), $parameters->get(FileChunk::CURRENT_CHUNK_SIZE));
        $this->assertSame($chunk->getNumber(), $parameters->get(FileChunk::CHUNK_NUMBER));
        $this->assertSame($chunk->getTotalSize(), $parameters->get(FileChunk::TOTAL_SIZE));
        $this->assertSame($chunk->getUniqueId(), $parameters->get(FileChunk::UNIQUE_ID));
    }

    public function testWrongCreation(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $parameters = new ParameterBag([
            FileChunk::CHUNK_SIZE => 150,
            FileChunk::CURRENT_CHUNK_SIZE => 100,
            FileChunk::CHUNK_NUMBER => 1,
            FileChunk::TOTAL_SIZE => 65535,
        ]);
        $file = $this->getFile();
        $request = Request::create('/upload');
        $request->request = $parameters;

        FileChunk::create($request, $file);
    }
}
