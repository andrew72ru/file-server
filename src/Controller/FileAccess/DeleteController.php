<?php declare(strict_types=1);
/**
 * 14.05.2020.
 */

namespace App\Controller\FileAccess;

use League\Flysystem\FileNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\{HttpFoundation\HeaderBag,
    HttpFoundation\Request,
    HttpFoundation\Response,
    HttpKernel\Exception\AccessDeniedHttpException,
    HttpKernel\Exception\NotFoundHttpException,
    Routing\Annotation\Route};

/**
 * Remove file.
 *
 * @Route(name="delete_file", path="/delete/{type}/{filename}", methods={"DELETE"})
 */
class DeleteController extends AbstractFileAccessController
{
    public const SECURITY_HEADER = 'X-Security-Token';

    private LoggerInterface $logger;

    /**
     * @param array           $filesystems
     * @param LoggerInterface $logger
     */
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
    public function __invoke(Request $request, string $type, string $filename)
    {
        $this->logger->info(\sprintf('Try to delete \'%s\' file from \'%s\' category', $filename, $type));
        $fs = $this->getFs($type);

        $this->checkSecurityHeader($request->headers);
        try {
            $fs->delete($filename);
            $this->logger->info(\sprintf('File \'%s\' was deleted from \'%s\'', $filename, $type), \array_merge($request->headers->all(), $request->server->all()));

            return new Response(null, Response::HTTP_NO_CONTENT);
        } catch (FileNotFoundException $e) {
            throw new NotFoundHttpException(\sprintf('File \'%s\' not found in \'%s\'', $filename, $type));
        }
    }

    /**
     * Checks the request protection header.
     *
     * @param HeaderBag $headers
     */
    protected function checkSecurityHeader(HeaderBag $headers): void
    {
        if (!$headers->has(self::SECURITY_HEADER)) {
            throw new AccessDeniedHttpException('No security header provided');
        }

        $secret = $this->getParameter('security_header_secret');
        if ($headers->get(self::SECURITY_HEADER) !== $secret) {
            throw new AccessDeniedHttpException('Wrong security header');
        }
    }
}
