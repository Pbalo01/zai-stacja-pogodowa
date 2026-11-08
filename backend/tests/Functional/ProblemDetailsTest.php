<?php

namespace App\Tests\Functional;

final class ProblemDetailsTest extends ApiTestCase
{
    public function testUnknownResourceReturns404(): void
    {
        $this->assertProblem($this->request('GET', '/api/series/999999'), 404);
        $this->assertProblem($this->request('GET', '/api/nie-ma-takiej-sciezki'), 404);
    }

    public function testMalformedJsonReturns400(): void
    {
        $response = $this->request('POST', '/api/series', '{"name":', ['Content-Type' => 'application/json'] + $this->bearer($this->login()));

        $this->assertProblem($response, 400);
    }

    public function testUnacceptableFormatReturns406(): void
    {
        $this->client->request('GET', '/api/series', [], [], ['HTTP_ACCEPT' => 'application/xml']);

        $this->assertProblem($this->client->getResponse(), 406);
    }

    public function testNonJsonBodyReturns415(): void
    {
        $this->assertProblem($this->request('POST', '/api/measurements', 'value=1', ['Content-Type' => 'text/plain']), 415);
    }

    public function testValidationErrorsListFields(): void
    {
        $response = $this->request('POST', '/api/series', ['minValue' => 'x', 'color' => 'zly'], $this->bearer($this->login()));

        $this->assertProblem($response, 422);
        $fields = array_column($this->json($response)['errors'], 'field');
        self::assertContains('minValue', $fields);
    }

    public function testSecurityHeaders(): void
    {
        $response = $this->request('GET', '/api/health');

        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertNotNull($response->headers->get('Content-Security-Policy'));
        self::assertSame(['status' => 'ok'], $this->json($response));
    }
}
