<?php

namespace App\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Renders the HTTP errors as application/problem+json (RFC 9457), with the message of the exception as detail.
 * The other errors stay 500, rendered by Symfony. Priority -1: after the log of the exception by Symfony.
 */
#[AsEventListener(priority: -1)]
final class ProblemJsonListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof HttpExceptionInterface) {
            return;
        }

        $status = $exception->getStatusCode();
        $event->setResponse(new JsonResponse([
            'type' => 'about:blank',
            'title' => Response::$statusTexts[$status] ?? 'Error',
            'status' => $status,
            'detail' => $exception->getMessage(),
        ], $status, ['Content-Type' => 'application/problem+json'] + $exception->getHeaders()));
    }
}
