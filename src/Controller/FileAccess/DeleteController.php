<?php declare(strict_types=1);

namespace App\Controller\FileAccess;

use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;
use Symfony\Component\{HttpFoundation\HeaderBag,
    HttpFoundation\Request,
    HttpFoundation\Response,
    HttpKernel\Exception\AccessDeniedHttpException,
    HttpKernel\Exception\NotFoundHttpException,
    Routing\Annotation\Route};

#[Route(path: '/delete/{type}/{filename}', name: 'delete_file', requirements: ['filename' => '.+'], methods: ['DELETE'])]
class DeleteController extends AbstractFileAccessController
{
    public const SECURITY_HEADER = 'X-Security-Token';

    public function __construct(array $filesystems, private LoggerInterface $logger)
    {
        parent::__construct($filesystems);
    }

    public function __invoke(Request $request, string $type, string $filename): Response
    {
        $this->logger->info(\sprintf('Try to delete \'%s\' file from \'%s\' category', $filename, $type));
        $fs = $this->getFs($type);

        $this->checkSecurityHeader($request->headers);
        try {
            $fs->delete($filename);
            $this->logger->info(\sprintf('File \'%s\' was deleted from \'%s\'', $filename, $type), \array_merge($request->headers->all(), $request->server->all()));

            return new Response(null, Response::HTTP_NO_CONTENT);
        } catch (FilesystemException $e) {
            throw new NotFoundHttpException(\sprintf('File \'%s\' not found in \'%s\'', $filename, $type));
        }
    }

    /**
     * Checks the request protection header.
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
