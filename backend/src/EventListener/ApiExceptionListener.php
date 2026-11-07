<?php

namespace App\EventListener;

use App\Exception\ValidationProblemException;
use App\Http\ProblemResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

// Priorytet niższy niż listener Security (1), który zamienia AccessDeniedException
// na 401/403, ale wyższy niż domyślny ErrorListener Symfony (-128).
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -64)]
final class ApiExceptionListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();
        $errors = [];
        $headers = [];

        if ($exception instanceof ValidationProblemException) {
            $status = 422;
            $detail = 'Dane nie spełniają reguł walidacji.';
            $errors = $exception->getErrors();
        } elseif ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $headers = $exception->getHeaders();
            $detail = $this->describe($exception, $status);
            $previous = $exception->getPrevious();
            if ($previous instanceof ValidationFailedException) {
                $detail = 'Dane nie spełniają reguł walidacji.';
                $errors = $this->violationsToErrors($previous->getViolations());
            }
        } elseif ($exception instanceof AuthenticationException) {
            $status = 401;
            $detail = 'Wymagane uwierzytelnienie.';
        } elseif ($exception instanceof AccessDeniedException) {
            $status = 403;
            $detail = 'Brak uprawnień do tej operacji.';
        } else {
            // Nieoczekiwany błąd: szczegóły tylko w logu, nigdy w odpowiedzi
            $status = 500;
            $detail = 'Wystąpił nieoczekiwany błąd serwera.';
            $this->logger->error('Nieobsłużony wyjątek: {message}', ['message' => $exception->getMessage(), 'exception' => $exception]);
        }

        $event->setResponse(new ProblemResponse($status, $detail, $errors, $headers));
    }

    private function describe(HttpExceptionInterface $exception, int $status): string
    {
        $previous = $exception->getPrevious();

        return match (true) {
            $previous instanceof ResourceNotFoundException => 'Nie znaleziono zasobu.',
            $previous instanceof MethodNotAllowedException => 'Metoda HTTP nie jest dozwolona dla tego zasobu.',
            $status >= 500 => 'Wystąpił błąd serwera.',
            default => $exception->getMessage(),
        };
    }

    /** @return list<array{field: string, message: string}> */
    private function violationsToErrors(ConstraintViolationListInterface $violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[] = [
                'field' => $violation->getPropertyPath() ?: '(body)',
                'message' => (string) $violation->getMessage(),
            ];
        }

        return $errors;
    }
}
