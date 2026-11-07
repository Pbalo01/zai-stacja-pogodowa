<?php

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

// Problem Details (RFC 9457)
class ProblemResponse extends JsonResponse
{
    /**
     * @param list<array{field: string, message: string}> $errors
     */
    public function __construct(int $status, ?string $detail = null, array $errors = [], array $headers = [])
    {
        $data = [
            'type' => 'about:blank',
            'title' => Response::$statusTexts[$status] ?? 'Error',
            'status' => $status,
        ];
        if (null !== $detail && '' !== $detail) {
            $data['detail'] = $detail;
        }
        if ($errors) {
            $data['errors'] = $errors;
        }

        parent::__construct($data, $status, ['Content-Type' => 'application/problem+json'] + $headers);
        $this->setEncodingOptions(self::DEFAULT_ENCODING_OPTIONS | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }
}
