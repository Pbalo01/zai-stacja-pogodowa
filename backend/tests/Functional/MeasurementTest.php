<?php

namespace App\Tests\Functional;

final class MeasurementTest extends ApiTestCase
{
    private string $token;

    /** @var array<string, mixed> */
    private array $series;

    private string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = $this->login();
        $this->series = $this->createSeries($this->token, ['minValue' => 0, 'maxValue' => 10]);
        $this->apiKey = $this->createSensor($this->token, $this->series['id'])['apiKey'];
    }

    public function testSensorKeyStoresMeasurement(): void
    {
        $response = $this->sendMeasurement($this->apiKey, ['value' => 7.5, 'timestamp' => '2026-01-10T10:00:00Z']);

        self::assertSame(201, $response->getStatusCode());
        $measurement = $this->json($response);
        self::assertSame('/api/measurements/'.$measurement['id'], $response->headers->get('Location'));
        self::assertSame($this->series['id'], $measurement['seriesId']);
        self::assertSame(7.5, $measurement['value']);
        self::assertSame(strtotime('2026-01-10T10:00:00Z'), strtotime($measurement['timestamp']));

        $read = $this->json($this->request('GET', '/api/measurements/'.$measurement['id']));
        self::assertSame(7.5, $read['value']);
    }

    public function testMissingTimestampUsesServerTime(): void
    {
        $measurement = $this->json($this->sendMeasurement($this->apiKey, ['value' => 1]));

        self::assertLessThan(60, abs(time() - strtotime($measurement['timestamp'])));
    }

    public function testValueOutsideSeriesRangeReturns422AndIsNotStored(): void
    {
        $response = $this->sendMeasurement($this->apiKey, ['value' => 11]);

        $this->assertProblem($response, 422);
        self::assertSame('value', $this->json($response)['errors'][0]['field']);
        self::assertSame([], $this->json($this->request('GET', '/api/measurements?series='.$this->series['id'])));
    }

    public function testBoundaryValuesAreAccepted(): void
    {
        self::assertSame(201, $this->sendMeasurement($this->apiKey, ['value' => 0])->getStatusCode());
        self::assertSame(201, $this->sendMeasurement($this->apiKey, ['value' => 10])->getStatusCode());
    }

    public function testInvalidBodiesAreRejected(): void
    {
        $this->assertProblem($this->sendMeasurement($this->apiKey, []), 422);
        $this->assertProblem($this->sendMeasurement($this->apiKey, ['value' => 'abc']), 422);
        $this->assertProblem($this->sendMeasurement($this->apiKey, ['value' => 1, 'timestamp' => 'wczoraj']), 422);
        $future = (new \DateTimeImmutable('+1 hour'))->format(\DATE_ATOM);
        $this->assertProblem($this->sendMeasurement($this->apiKey, ['value' => 1, 'timestamp' => $future]), 422);
    }

    public function testMissingOrInvalidKeyReturns401(): void
    {
        $this->assertProblem($this->sendMeasurement(null, ['value' => 1]), 401);
        $this->assertProblem($this->sendMeasurement(str_repeat('x', 48), ['value' => 1]), 401);
    }

    public function testAdminTokenInsteadOfKeyReturns401(): void
    {
        $this->assertProblem($this->sendMeasurement(null, ['value' => 1], $this->bearer($this->token)), 401);
    }

    public function testMeasurementsCannotBeModifiedOrDeleted(): void
    {
        $id = $this->json($this->sendMeasurement($this->apiKey, ['value' => 3]))['id'];

        foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
            $body = 'DELETE' === $method ? null : ['value' => 4];
            $this->assertProblem($this->request($method, '/api/measurements/'.$id, $body, $this->bearer($this->token)), 405);
        }

        self::assertSame(3.0, (float) $this->json($this->request('GET', '/api/measurements/'.$id))['value']);
    }

    public function testFilteringAndSorting(): void
    {
        foreach (['2026-01-10T12:00:00Z' => 3, '2026-01-10T10:00:00Z' => 1, '2026-01-10T11:00:00Z' => 2] as $timestamp => $value) {
            $this->sendMeasurement($this->apiKey, ['value' => $value, 'timestamp' => $timestamp]);
        }
        $other = $this->createSeries($this->token);
        $this->sendMeasurement($this->createSensor($this->token, $other['id'])['apiKey'], ['value' => 50, 'timestamp' => '2026-01-10T10:00:00Z']);

        $values = fn (string $query) => array_map('floatval', array_column($this->json($this->request('GET', '/api/measurements?'.$query)), 'value'));
        $id = $this->series['id'];

        self::assertSame([1.0, 2.0, 3.0], $values("series=$id"));
        self::assertSame([3.0, 2.0, 1.0], $values("series=$id&sort=-timestamp"));
        self::assertSame([1.0, 2.0], $values("series=$id&from=2026-01-10T10:00:00Z&to=2026-01-10T11:00:00Z"));
        self::assertSame([1.0], $values("series=$id&limit=1"));
        self::assertCount(4, $values("series=$id,{$other['id']}"));
    }

    public function testInvalidQueryParametersReturn400(): void
    {
        foreach (['from=nie-data', 'to=2026-13-01T00:00:00Z', 'sort=value', 'limit=0', 'limit=10001', 'series=a,b'] as $query) {
            $this->assertProblem($this->request('GET', '/api/measurements?'.$query), 400);
        }
    }
}
