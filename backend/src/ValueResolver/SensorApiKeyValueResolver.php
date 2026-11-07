<?php

namespace App\ValueResolver;

use App\Entity\Sensor;
use App\Repository\SensorRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsTargetedValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

// Działa poza firewallem Bearer: token administratora nie zastępuje klucza czujnika.
// Użycie: #[ValueResolver('sensor_api_key')] Sensor $sensor
#[AsTargetedValueResolver('sensor_api_key')]
final class SensorApiKeyValueResolver implements ValueResolverInterface
{
    public function __construct(private readonly SensorRepository $sensors)
    {
    }

    /** @return iterable<Sensor> */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $apiKey = trim((string) $request->headers->get('X-API-Key'));
        if ('' === $apiKey) {
            throw new UnauthorizedHttpException('ApiKey', 'Brak klucza API czujnika (nagłówek X-API-Key).');
        }

        $sensor = $this->sensors->findOneBy(['apiKeyHash' => hash('sha256', $apiKey)]);
        if (null === $sensor) {
            throw new UnauthorizedHttpException('ApiKey', 'Nieprawidłowy klucz API czujnika.');
        }

        return [$sensor];
    }
}
