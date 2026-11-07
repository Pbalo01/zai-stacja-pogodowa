<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Błąd reguły semantycznej sprawdzanej w kontrolerze (np. wartość spoza
 * przedziału serii) – kończy się kodem 422 z listą errors[{field, message}].
 */
class ValidationProblemException extends UnprocessableEntityHttpException
{
    public function __construct(private readonly string $field, string $message)
    {
        parent::__construct($message);
    }

    /** @return list<array{field: string, message: string}> */
    public function getErrors(): array
    {
        return [['field' => $this->field, 'message' => $this->getMessage()]];
    }
}
