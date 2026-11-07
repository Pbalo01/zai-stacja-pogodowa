<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

// Reguła sprawdzana w kontrolerze (np. wartość spoza przedziału serii) → 422 z errors.
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
