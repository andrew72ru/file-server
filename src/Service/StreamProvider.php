<?php declare(strict_types=1);

namespace App\Service;

use App\Service\Exception\{AdapterNotFoundException, NotImplementedException};
use Aws\ResultInterface;
use Aws\S3\S3ClientInterface;
use GuzzleHttp\Psr7\Stream;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Psr\Http\Message\StreamInterface;

class StreamProvider
{
    /**
     * @var FilesystemAdapter[]
     */
    private iterable $adapters;
    private S3ClientInterface $s3Client;
    private string $bucket;

    /**
     * @param FilesystemAdapter[] $adapters
     */
    public function __construct(iterable $adapters, S3ClientInterface $s3Client, string $bucket)
    {
        $this->adapters = $adapters;
        $this->s3Client = $s3Client;
        $this->bucket = $bucket;
    }

    public function getStream(string $path, string $type): StreamInterface
    {
        $adapter = $this->getAdapter($type);
        switch (true) {
            case $adapter instanceof AwsS3V3Adapter:
                return $this->getS3Stream($path);
            case $adapter instanceof LocalFilesystemAdapter:
                return $this->getLocalStream($path);
            default:
                throw new NotImplementedException(\sprintf('Process with \'%s\' adapter not implemented yet', \get_class($adapter)));
        }
    }

    private function getLocalStream(string $path): StreamInterface
    {
        return new Stream(\fopen($path, 'rb'));
    }

    private function getS3Stream(string $path): StreamInterface
    {
        $result = $this->s3Client->getObject([
            'Bucket' => $this->bucket,
            'Key' => $path,
        ]);

        if (!$result instanceof ResultInterface) {
            throw new \InvalidArgumentException('Unable to load result from S3');
        }
        $body = $result['Body'] ?? null;
        if (!$body instanceof StreamInterface) {
            throw new \InvalidArgumentException('Unable to load response body from result');
        }

        return $body;
    }

    private function getAdapter(string $type): FilesystemAdapter
    {
        foreach ($this->adapters as $name => $adapter) {
            if ($name === $type) {
                return $adapter;
            }
        }

        throw new AdapterNotFoundException(\sprintf('Adapter for \'%s\' not found', $type));
    }
}
