<?php
namespace App\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'kernel.exception', priority: 0)]
final class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $req = $event->getRequest();
        if (!str_starts_with($req->getPathInfo(), '/api') && $req->headers->get('Accept') !== 'application/json') {
            return;
        }

        $e = $event->getThrowable();
        $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

        $event->setResponse(new JsonResponse([
            'error' => [
                'type' => (new \ReflectionClass($e))->getShortName(),
                'message' => $e->getMessage(),
            ],
        ], $status));
    }
}
