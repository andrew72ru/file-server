<?php
/**
 * 04.05.2020.
 */

declare(strict_types=1);

namespace App\Controller\FileAccess;

use App\Service\ByteFormatter;
use League\Flysystem\FileNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\{HeaderUtils, Request, Response, StreamedResponse};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Download as file.
 *
 * @Route(name="download_file", path="/download/{type}/{filename}")
 */
class DownloadController extends AbstractFileAccessController
{
    protected const BUFFER_SIZE = 1024 * 86;

    private LoggerInterface $logger;

    public function __construct(array $filesystems, LoggerInterface $logger)
    {
        parent::__construct($filesystems);
        $this->logger = $logger;
    }

    /**
     * @param Request $request
     * @param string  $type
     * @param string  $filename
     *
     * @return Response
     */
    public function __invoke(Request $request, string $type, string $filename): Response
    {
        $this->logger->info(\sprintf('Try to download %s from %s type', $filename, $type));

        $fs = $this->getFs($type);
        try {
            $fileStream = $fs->readStream($filename);
            $fileSize = $fs->getSize($filename);
            $mimeType = $fs->getMimetype($filename);
        } catch (FileNotFoundException $e) {
            throw new NotFoundHttpException(\sprintf('File \'%s\' not found', $filename));
        }

        $response = new StreamedResponse();
        $response->headers->set('Content-Length', (string) $fileSize);

        if (\strpos($mimeType, 'video') === 0) {
            $response->headers->set('Content-type', $mimeType);

            return $this->makeVideoStream($request, $fileSize, $fileStream, $response);
        }

        if (\strpos($mimeType, 'image') === 0) {
            $response->headers->set('Content-type', $mimeType);
        } else {
            $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename);
            $response->headers->set('Content-Disposition', $disposition);
        }

        $this->setCommonHeaders($response, 0, ($fileSize - 1), $fileSize);
        $response->setCallback(function () use ($fileStream, $fileSize) {
            $this->makeStream($fileStream, 0, $fileSize - 1);
        });

        return $response;
    }

    /**
     * @param Request          $request
     * @param int              $size
     * @param mixed            $resource
     * @param StreamedResponse $response
     *
     * @return Response
     */
    protected function makeVideoStream(Request $request, int $size, $resource, StreamedResponse $response): Response
    {
        $this->logger->info('Video stream request', [
            'HTTP_RANGE' => $request->server->get('HTTP_RANGE'),
        ]);

        if (!\is_resource($resource)) {
            $message = \sprintf('Action %s needs a resource as first argument, %s given', __METHOD__, (\is_object($resource) ? \get_class($resource) : \gettype($resource)));
            throw new \RuntimeException($message);
        }

        $start = 0;
        $end = $size - 1;
        $this->setCommonHeaders($response, $start, $end, $size);

        $rangeHeader = $request->server->get('HTTP_RANGE', null) ?? $request->headers->get('Http-Range', null);
        if ($rangeHeader !== null) {
            $cEnd = $end;

            [, $range] = \explode('=', $rangeHeader, 2);
            if (\strpos($range, ',') !== false) {
                return $this->wrongRangeDescription($response, $start, $end, $size, $range);
            }
            $this->logger->info('Request range', ['range' => $range]);

            $range = \explode('-', $range);
            $cStart = (int) $range[0];
            $cEnd = !empty($range[1] ?? null) ? (int) $range[1] : $cEnd;
            $cEnd = ($cEnd > $end) ? $end : $cEnd;

            if ($cStart > $cEnd || $cStart > $size - 1 || $cEnd > $size) {
                return $this->wrongRangeRequested($response, $cStart, $cEnd, $size, $start, $end);
            }

            $start = $cStart;
            $end = $cEnd;
            $response->setStatusCode(Response::HTTP_PARTIAL_CONTENT);
            $response->headers->set('Content-Length', (string) ($end - $start + 1));
            $response->headers->set('Content-Range', \sprintf('bytes %s-%s/%s', $start, $end, $size));
        }

        $this->logger->info(\sprintf('Requested bytes from %d to %d', ByteFormatter::format($start), ByteFormatter::format($end)));
        $this->logger->info('Response headers', $response->headers->all());

        $response->setCallback(function () use ($resource, $start, $end) {
            $this->makeStream($resource, $start, $end);
        });

        return $response;
    }

    /**
     * Write part of stream to stdout.
     *
     * @param resource $resource
     * @param int      $start
     * @param int      $end
     */
    private function makeStream($resource, int $start, int $end): void
    {
        \set_time_limit(0);
        \fseek($resource, $start);
        $outputStream = \fopen('php://output', 'wb');
        $i = $start;
        $this->logger->info('Callback info', [
            'place' => \ftell($resource),
            'start' => $i,
            'end' => $end,
        ]);

        while (!\feof($resource) && $i <= $end) {
            $bytesToRead = self::BUFFER_SIZE;
            if (($i + $bytesToRead) > $end) {
                $bytesToRead = $end - $i + 1;
            }

            \fwrite($outputStream, \fread($resource, $bytesToRead));
            \flush();
            $i += $bytesToRead;
        }
        $info = \vsprintf('Current memory usage %s, peak memory usage %s', [
            ByteFormatter::format(\memory_get_usage(true)),
            ByteFormatter::format(\memory_get_peak_usage(true)),
        ]);
        $this->logger->info($info);
    }

    /**
     * Method only for error.
     *
     * @param StreamedResponse $response
     * @param int              $cStart   Calculated start
     * @param int              $cEnd     Calculated end
     * @param int              $size     Current size
     * @param int              $start    Native start byte
     * @param int              $end      Native end byte
     *
     * @return Response
     */
    protected function wrongRangeRequested(StreamedResponse $response, int $cStart, int $cEnd, int $size, int $start, int $end): Response
    {
        $response->setCallback(fn () => null);
        $response->setStatusCode(Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE);
        $response->headers->set('Content-Range', \sprintf('bytes %s-%s/%s', $start, $end, $size));

        $this->logger->info('Response broken on collation', [
            'cStart > cEnd' => (bool) $cStart > $cEnd,
            'cStart > size-1' => (bool) $cStart > $size - 1,
            'cEnd > size' => (bool) $cEnd > $size,
            'cStart' => $cStart,
            'cEnd' => $cEnd,
            'size' => $size,
        ]);
        $this->logger->info(\sprintf('Response status %d', $response->getStatusCode()));
        $this->logger->info('Response headers', $response->headers->all());

        return $response;
    }

    /**
     * Method only for error.
     *
     * @param StreamedResponse $response
     * @param int              $start    Start byte
     * @param int              $end      End byte
     * @param int              $size     Current size
     * @param string           $range    Received range
     *
     * @return Response
     */
    protected function wrongRangeDescription(StreamedResponse $response, int $start, int $end, int $size, string $range): Response
    {
        $response->setCallback(fn () => null);
        $response->setStatusCode(Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE);
        $response->headers->set('Content-Range', \sprintf('bytes %s-%s/%s', $start, $end, $size));

        $this->logger->info(\sprintf('Response broken at strpos(%s, \',\')', $range));
        $this->logger->info(\sprintf('Response status %d', $response->getStatusCode()));
        $this->logger->info('Response headers', $response->headers->all());

        return $response;
    }

    protected function setCommonHeaders(Response $response, int $start, int $end, int $size): void
    {
        $response->headers->set('Cache-Control', \sprintf('max-age=%d, public', (60 * 60 * 24 * 30))); // 30 days
        $response->headers->set('Expires', \date_create()->add(\date_interval_create_from_date_string('+30 days'))->format('D, d M Y H:i:s'));
        $response->headers->set('Accept-Ranges', \sprintf('bytes 0-%d', $size));
        $response->headers->set('Content-Range', \sprintf('bytes %d-%d/%d', $start, $end, $size));
        $response->headers->set('Content-Length', (string) $size);
    }
}
