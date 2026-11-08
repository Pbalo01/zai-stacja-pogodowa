<?php

namespace App\Tests\Functional;

final class SeriesTest extends ApiTestCase
{
    public function testCrud(): void
    {
        $token = $this->login();

        $response = $this->request('POST', '/api/series', [
            'name' => 'Wilgotność', 'minValue' => 0, 'maxValue' => 100, 'color' => '#1f77b4', 'unit' => '%',
        ], $this->bearer($token));
        self::assertSame(201, $response->getStatusCode());
        $series = $this->json($response);
        self::assertSame('/api/series/'.$series['id'], $response->headers->get('Location'));
        self::assertSame('%', $series['unit']);

        $list = $this->json($this->request('GET', '/api/series'));
        self::assertSame([$series], $list);

        $update = ['name' => 'Wilgotność – salon', 'minValue' => 10, 'maxValue' => 90, 'color' => '#ff7f0e'];
        $response = $this->request('PUT', '/api/series/'.$series['id'], $update, $this->bearer($token));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Wilgotność – salon', $this->json($response)['name']);

        $shown = $this->json($this->request('GET', '/api/series/'.$series['id']));
        self::assertSame('#ff7f0e', $shown['color']);

        self::assertSame(204, $this->request('DELETE', '/api/series/'.$series['id'], null, $this->bearer($token))->getStatusCode());
        $this->assertProblem($this->request('GET', '/api/series/'.$series['id']), 404);
    }

    public function testUpdateExcludingStoredMeasurementsReturns409(): void
    {
        $token = $this->login();
        $series = $this->createSeries($token);
        $sensor = $this->createSensor($token, $series['id']);
        self::assertSame(201, $this->sendMeasurement($sensor['apiKey'], ['value' => 75])->getStatusCode());

        $response = $this->request('PUT', '/api/series/'.$series['id'], [
            'name' => 'Temperatura', 'minValue' => 0, 'maxValue' => 50, 'color' => '#1f77b4',
        ], $this->bearer($token));

        $this->assertProblem($response, 409);
        self::assertSame(100.0, (float) $this->json($this->request('GET', '/api/series/'.$series['id']))['maxValue']);
    }

    public function testDeleteRemovesMeasurementsAndSensors(): void
    {
        $token = $this->login();
        $series = $this->createSeries($token);
        $sensor = $this->createSensor($token, $series['id']);
        $measurement = $this->json($this->sendMeasurement($sensor['apiKey'], ['value' => 5]));

        self::assertSame(204, $this->request('DELETE', '/api/series/'.$series['id'], null, $this->bearer($token))->getStatusCode());

        $this->assertProblem($this->request('GET', '/api/measurements/'.$measurement['id']), 404);
        $this->assertProblem($this->request('GET', '/api/sensors/'.$sensor['id'], null, $this->bearer($token)), 404);
        $this->assertProblem($this->sendMeasurement($sensor['apiKey'], ['value' => 5]), 401);
    }

    public function testMinNotLowerThanMaxReturns422(): void
    {
        $token = $this->login();

        $response = $this->request('POST', '/api/series', ['name' => 'X', 'minValue' => 10, 'maxValue' => 10, 'color' => '#000000'], $this->bearer($token));

        $this->assertProblem($response, 422);
        self::assertSame('maxValue', $this->json($response)['errors'][0]['field']);
    }

    public function testModificationsWithoutTokenReturn401(): void
    {
        $series = $this->createSeries($this->login());
        $body = ['name' => 'X', 'minValue' => 0, 'maxValue' => 1, 'color' => '#000000'];

        $this->assertProblem($this->request('POST', '/api/series', $body), 401);
        $this->assertProblem($this->request('PUT', '/api/series/'.$series['id'], $body), 401);
        $this->assertProblem($this->request('DELETE', '/api/series/'.$series['id']), 401);
    }
}
