<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ApiTestCase extends WebTestCase
{
    protected const ADMIN_USERNAME = 'admin';
    protected const ADMIN_PASSWORD = 'Haslo-testowe-123';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        foreach (['Measurement', 'Sensor', 'Series', 'ApiToken', 'User'] as $entity) {
            $em->createQuery(sprintf('DELETE FROM App\Entity\%s e', $entity))->execute();
        }

        $admin = new User(self::ADMIN_USERNAME);
        $admin->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($admin, self::ADMIN_PASSWORD));
        $em->persist($admin);
        $em->flush();
    }

    /**
     * @param array<string, string> $headers
     */
    protected function request(string $method, string $uri, mixed $body = null, array $headers = []): Response
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $name => $value) {
            $key = strtoupper(str_replace('-', '_', $name));
            $server['Content-Type' === $name ? 'CONTENT_TYPE' : 'HTTP_'.$key] = $value;
        }

        $content = null;
        if (is_string($body)) {
            $content = $body;
        } elseif (null !== $body) {
            $content = json_encode($body, \JSON_THROW_ON_ERROR);
            $server['CONTENT_TYPE'] ??= 'application/json';
        }

        $this->client->request($method, $uri, [], [], $server, $content);

        return $this->client->getResponse();
    }

    /**
     * @return array<mixed>
     */
    protected function json(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    protected function login(): string
    {
        $response = $this->request('POST', '/api/auth/login', ['username' => self::ADMIN_USERNAME, 'password' => self::ADMIN_PASSWORD]);
        self::assertSame(200, $response->getStatusCode());

        return $this->json($response)['accessToken'];
    }

    /**
     * @return array<string, string>
     */
    protected function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    protected function createSeries(string $token, array $override = []): array
    {
        $body = $override + ['name' => 'Temperatura', 'minValue' => 0, 'maxValue' => 100, 'color' => '#1f77b4'];
        $response = $this->request('POST', '/api/series', $body, $this->bearer($token));
        self::assertSame(201, $response->getStatusCode());

        return $this->json($response);
    }

    /**
     * @return array<string, mixed>
     */
    protected function createSensor(string $token, int $seriesId): array
    {
        $response = $this->request('POST', '/api/sensors', ['name' => 'Czujnik', 'seriesId' => $seriesId], $this->bearer($token));
        self::assertSame(201, $response->getStatusCode());

        return $this->json($response);
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function sendMeasurement(?string $apiKey, array $body, array $headers = []): Response
    {
        if (null !== $apiKey) {
            $headers['X-API-Key'] = $apiKey;
        }

        return $this->request('POST', '/api/measurements', $body, $headers);
    }

    protected function assertProblem(Response $response, int $status): void
    {
        self::assertSame($status, $response->getStatusCode());
        self::assertStringStartsWith('application/problem+json', (string) $response->headers->get('Content-Type'));

        $problem = $this->json($response);
        self::assertSame($status, $problem['status']);
        self::assertIsString($problem['title']);
        self::assertSame('about:blank', $problem['type']);
        self::assertArrayHasKey('detail', $problem);
    }
}
