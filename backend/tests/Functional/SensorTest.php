<?php

namespace App\Tests\Functional;

final class SensorTest extends ApiTestCase
{
    public function testRegistrationReturnsApiKeyOnlyOnce(): void
    {
        $token = $this->login();
        $series = $this->createSeries($token);

        $response = $this->request('POST', '/api/sensors', ['name' => 'Czujnik salon', 'seriesId' => $series['id']], $this->bearer($token));
        self::assertSame(201, $response->getStatusCode());
        $sensor = $this->json($response);
        self::assertSame('/api/sensors/'.$sensor['id'], $response->headers->get('Location'));
        self::assertSame($series['id'], $sensor['seriesId']);
        self::assertSame(48, strlen($sensor['apiKey']));

        $list = $this->request('GET', '/api/sensors', null, $this->bearer($token));
        $one = $this->request('GET', '/api/sensors/'.$sensor['id'], null, $this->bearer($token));
        foreach ([$list, $one] as $read) {
            self::assertSame(200, $read->getStatusCode());
            self::assertStringNotContainsString($sensor['apiKey'], (string) $read->getContent());
            self::assertStringNotContainsString('apiKeyHash', (string) $read->getContent());
        }
        self::assertSame($sensor['id'], $this->json($list)[0]['id']);
    }

    public function testRegistrationForMissingSeriesReturns422(): void
    {
        $response = $this->request('POST', '/api/sensors', ['name' => 'Czujnik', 'seriesId' => 999999], $this->bearer($this->login()));

        $this->assertProblem($response, 422);
        self::assertSame('seriesId', $this->json($response)['errors'][0]['field']);
    }

    public function testSensorsRequireAdmin(): void
    {
        $this->assertProblem($this->request('GET', '/api/sensors'), 401);
        $this->assertProblem($this->request('POST', '/api/sensors', ['name' => 'X', 'seriesId' => 1]), 401);
    }

    public function testDeletedSensorKeyStopsWorkingButMeasurementsStay(): void
    {
        $token = $this->login();
        $sensor = $this->createSensor($token, $this->createSeries($token)['id']);
        $measurement = $this->json($this->sendMeasurement($sensor['apiKey'], ['value' => 20]));

        self::assertSame(204, $this->request('DELETE', '/api/sensors/'.$sensor['id'], null, $this->bearer($token))->getStatusCode());

        $this->assertProblem($this->sendMeasurement($sensor['apiKey'], ['value' => 20]), 401);
        $kept = $this->request('GET', '/api/measurements/'.$measurement['id']);
        self::assertSame(200, $kept->getStatusCode());
        self::assertNull($this->json($kept)['sensorId']);
    }
}
