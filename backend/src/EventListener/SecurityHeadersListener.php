<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

// API nie osadza żadnych zasobów, więc dostaje najostrzejsze CSP.
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class SecurityHeadersListener
{
    private const API_CSP = "default-src 'none'; frame-ancestors 'none'";
    private const APP_CSP = "default-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

    public function __invoke(ResponseEvent $event): void
    {
        $headers = $event->getResponse()->headers;
        $isApi = str_starts_with($event->getRequest()->getPathInfo(), '/api');

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Frame-Options', 'DENY');
        if (!$headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', $isApi ? self::API_CSP : self::APP_CSP);
        }
    }
}
