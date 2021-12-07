<?php declare(strict_types=1);

namespace App\Controller\FileAccess;

use App\Service\ByteFormatter;
use App\Service\StreamProvider;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\{HeaderUtils, Request, Response, StreamedResponse};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Download as file.
 *
 * @Route(name="download_file", path="/download/{type}/{filename}", requirements={"filename"=".+"})
 */
class DownloadController extends AbstractFileAccessController
{
    protected const BUFFER_SIZE = 1024 * 86;
    protected const VIDEO_DEMO_NAME = 'video-demo-74df86.mp4';
    protected const IMAGE_DEMO_NAME = 'image-demo-775fdA.jpg';

    private LoggerInterface $logger;
    private StreamProvider $streamProvider;

    public function __construct(array $filesystems, LoggerInterface $logger, StreamProvider $streamProvider)
    {
        parent::__construct($filesystems);
        $this->logger = $logger;
        $this->streamProvider = $streamProvider;
    }

    public function __invoke(Request $request, string $type, string $filename): Response
    {
        $this->logger->info(\sprintf('Try to download %s from %s type', $filename, $type));
        $fs = $this->getFs($type);

        if (\strpos($filename, self::IMAGE_DEMO_NAME) === 0 || \strpos($filename, self::VIDEO_DEMO_NAME) === 0) {
            $path = \sprintf('%s/config/demo/%s', $this->getParameter('kernel.project_dir'), $filename);
            $fileSize = \filesize($path);
            $mimeType = \strpos($filename, self::IMAGE_DEMO_NAME) === 0 ? 'image/jpeg' : 'video/mp4';

            return $this->makeResponse($request, $this->streamProvider->getStream($path, $type), $fileSize, $mimeType, $filename);
        }

        try {
            $fileStream = $this->streamProvider->getStream($filename, $type);
            $fileSize = $fileStream->getSize() ?? 0;
            $mimeType = $fs->mimeType($filename);
        } catch (\Throwable $e) {
            throw new NotFoundHttpException($this->notFound($filename));
        }

        return $this->makeResponse($request, $fileStream, $fileSize, $mimeType, $filename);
    }

    protected function makeResponse(Request $request, StreamInterface $fileStream, int $fileSize, string $mimeType, string $filename): Response
    {
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

    protected function makeVideoStream(Request $request, int $size, StreamInterface $resource, StreamedResponse $response): Response
    {
        $this->logger->info('Video stream request', [
            'HTTP_RANGE' => $request->server->get('HTTP_RANGE'),
        ]);

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
            $response->headers->set('Content-Range', $this->getFormattedBytes($start, $end, $size));
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
     */
    private function makeStream(StreamInterface $resource, int $start, int $end): void
    {
        \set_time_limit(0);
        $resource->seek($start);
        $i = $start;
        $this->logger->info('Callback info', [
            'place' => $resource->tell(),
            'start' => $i,
            'end' => $end,
        ]);

        while (!$resource->eof() && $i <= $end) {
            $bytesToRead = self::BUFFER_SIZE;
            if (($i + $bytesToRead) > $end) {
                $bytesToRead = $end - $i + 1;
            }

            echo $resource->read($bytesToRead);
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
     * @param int $cStart Calculated start
     * @param int $cEnd   Calculated end
     * @param int $size   Current size
     * @param int $start  Native start byte
     * @param int $end    Native end byte
     */
    protected function wrongRangeRequested(StreamedResponse $response, int $cStart, int $cEnd, int $size, int $start, int $end): Response
    {
        $response->setCallback(fn () => null);
        $response->setStatusCode(Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE);
        $response->headers->set('Content-Range', $this->getFormattedBytes($start, $end, $size));

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
     * @param int    $start Start byte
     * @param int    $end   End byte
     * @param int    $size  Current size
     * @param string $range Received range
     */
    protected function wrongRangeDescription(StreamedResponse $response, int $start, int $end, int $size, string $range): Response
    {
        $response->setCallback(fn () => null);
        $response->setStatusCode(Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE);
        $response->headers->set('Content-Range', $this->getFormattedBytes($start, $end, $size));

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

    private function getFormattedBytes(int $start, int $end, int $size): string
    {
        return \sprintf('bytes %s-%s/%s', $start, $end, $size);
    }

    private function notFound(string $filename): string
    {
        return \sprintf('File \'%s\' not found', $filename);
    }
}
