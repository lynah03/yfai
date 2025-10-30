<?php
namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onException'];
    }

    public function onException(ExceptionEvent $event): void
    {
        $req = $event->getRequest();
        // On ne force le JSON que pour l'API
        if (!str_starts_with($req->getPathInfo(), '/api')) {
            return;
        }

        $e = $event->getThrowable();
        $status = 500;
        $headers = [];

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $headers = $e->getHeaders();
        }

        // Payload d’erreur minimal, stable et exploitable côté front
        $payload = [
            'error'   => $status >= 500 ? 'server_error' : 'error',
            'status'  => $status,
            'message' => $status >= 500 ? 'Something went wrong.' : $e->getMessage(),
        ];

        // En dev, tu peux exposer la trace pour debug (facultatif)
        if ($req->headers->get('X-Debug') === '1') {
            $payload['exception'] = [
                'type' => get_debug_type($e),
                'file' => $e->getFile().':'.$e->getLine(),
            ];
        }

        $event->setResponse(new JsonResponse($payload, $status, $headers));
    }
}
