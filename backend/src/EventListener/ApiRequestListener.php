<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

// Priorytet wyższy niż router (32), żeby 406/415 miały pierwszeństwo przed 404/405.
#[AsEventListener(event: KernelEvents::REQUEST, priority: 64)]
final class ApiRequestListener
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        if (!$this->acceptsJson($request)) {
            throw new NotAcceptableHttpException('API zwraca wyłącznie application/json.');
        }

        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)
            && '' !== $request->getContent()
            && !$this->isJson($request->headers->get('Content-Type', ''))) {
            throw new UnsupportedMediaTypeHttpException('Treść żądania musi mieć typ application/json.');
        }
    }

    private function acceptsJson(Request $request): bool
    {
        if ('' === trim((string) $request->headers->get('Accept'))) {
            return true;
        }

        foreach ($request->getAcceptableContentTypes() as $type) {
            if (in_array($type, ['*/*', 'application/*', 'application/json'], true) || str_ends_with($type, '+json')) {
                return true;
            }
        }

        return false;
    }

    private function isJson(string $contentType): bool
    {
        $mime = strtolower(trim(explode(';', $contentType)[0]));

        return 'application/json' === $mime || str_ends_with($mime, '+json');
    }
}
