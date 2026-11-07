<?php

namespace App\Controller;

use App\Dto\MeasurementInput;
use App\Entity\Measurement;
use App\Entity\Sensor;
use App\Exception\ValidationProblemException;
use App\Http\IsoDateTime;
use App\Repository\MeasurementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

// Celowo brak tras PUT/PATCH/DELETE – router odpowiada wtedy 405.
#[Route('/api/measurements')]
final class MeasurementController extends AbstractController
{
    private const DEFAULT_LIMIT = 1000;
    private const MAX_LIMIT = 10000;

    #[Route('', name: 'api_measurements_list', methods: ['GET'])]
    public function list(Request $request, MeasurementRepository $repository): JsonResponse
    {
        // Parametry parsujemy ręcznie, żeby błędne wartości dawały 400 (a nie 404)
        $query = $request->query;
        $seriesIds = $this->parseSeriesIds($query);
        $from = $this->parseDate($query, 'from');
        $to = $this->parseDate($query, 'to');
        if ($from && $to && $from > $to) {
            throw new BadRequestHttpException('Parametr from nie może być późniejszy niż to.');
        }

        $sort = (string) $query->get('sort', 'timestamp');
        if (!in_array($sort, ['timestamp', '-timestamp'], true)) {
            throw new BadRequestHttpException('Parametr sort przyjmuje wartości timestamp lub -timestamp.');
        }

        $limit = (string) $query->get('limit', (string) self::DEFAULT_LIMIT);
        if (!ctype_digit($limit) || (int) $limit < 1 || (int) $limit > self::MAX_LIMIT) {
            throw new BadRequestHttpException(sprintf('Parametr limit musi być liczbą całkowitą z przedziału 1..%d.', self::MAX_LIMIT));
        }

        $measurements = $repository->search($seriesIds, $from, $to, '-timestamp' === $sort, (int) $limit);

        return $this->json(array_map(static fn (Measurement $m) => $m->toArray(), $measurements));
    }

    #[Route('', name: 'api_measurements_create', methods: ['POST'])]
    public function create(
        #[ValueResolver('sensor_api_key')] Sensor $sensor,
        #[MapRequestPayload] MeasurementInput $input,
        EntityManagerInterface $em,
        LoggerInterface $sensorLogger,
    ): JsonResponse {
        $series = $sensor->getSeries();
        if (!$series->accepts($input->value)) {
            $sensorLogger->warning('Odrzucono wynik {value} czujnika #{sensor}: poza przedziałem [{min}, {max}] serii #{series}.', [
                'value' => $input->value,
                'sensor' => $sensor->getId(),
                'series' => $series->getId(),
                'min' => $series->getMinValue(),
                'max' => $series->getMaxValue(),
            ]);

            throw new ValidationProblemException('value', sprintf(
                'Wartość %s jest spoza przedziału serii [%s, %s].',
                $input->value, $series->getMinValue(), $series->getMaxValue(),
            ));
        }

        $measurement = new Measurement($series, $sensor, $input->value, $input->getTimestamp());
        $sensor->setLastMeasurementAt(new \DateTimeImmutable());
        $em->persist($measurement);
        $em->flush();

        $location = $this->generateUrl('api_measurements_show', ['id' => $measurement->getId()]);

        return $this->json($measurement->toArray(), Response::HTTP_CREATED, ['Location' => $location]);
    }

    #[Route('/{id}', name: 'api_measurements_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(#[MapEntity(message: 'Wynik nie istnieje.')] Measurement $measurement): JsonResponse
    {
        return $this->json($measurement->toArray());
    }

    /** @return int[] */
    private function parseSeriesIds(InputBag $query): array
    {
        $raw = trim((string) $query->get('series', ''));
        if ('' === $raw) {
            return [];
        }

        $ids = array_map('trim', explode(',', $raw));
        foreach ($ids as $id) {
            if (!ctype_digit($id)) {
                throw new BadRequestHttpException('Parametr series to lista identyfikatorów rozdzielonych przecinkami, np. 1,3.');
            }
        }

        return array_map('intval', $ids);
    }

    private function parseDate(InputBag $query, string $name): ?\DateTimeImmutable
    {
        if (!$query->has($name)) {
            return null;
        }

        $date = IsoDateTime::parse((string) $query->get($name));
        if (null === $date) {
            throw new BadRequestHttpException(sprintf('Parametr %s musi być datą ISO 8601 ze strefą czasową, np. 2026-10-15T08:30:00Z.', $name));
        }

        return $date;
    }
}
