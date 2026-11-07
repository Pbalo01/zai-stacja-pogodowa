<?php

namespace App\Controller;

use App\Dto\SensorInput;
use App\Entity\Sensor;
use App\Exception\ValidationProblemException;
use App\Repository\SensorRepository;
use App\Repository\SeriesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/sensors')]
#[IsGranted('ROLE_ADMIN')]
final class SensorController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('', name: 'api_sensors_list', methods: ['GET'])]
    public function list(SensorRepository $repository): JsonResponse
    {
        $sensors = $repository->findBy([], ['id' => 'ASC']);

        return $this->json(array_map(static fn (Sensor $s) => $s->toArray(), $sensors));
    }

    #[Route('', name: 'api_sensors_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] SensorInput $input, SeriesRepository $seriesRepository): JsonResponse
    {
        $series = $seriesRepository->find($input->seriesId);
        if (null === $series) {
            throw new ValidationProblemException('seriesId', 'Seria o podanym identyfikatorze nie istnieje.');
        }

        // 48 znaków szesnastkowych (192 bity losowości); w bazie tylko skrót SHA-256
        $apiKey = bin2hex(random_bytes(24));
        $sensor = new Sensor(trim($input->name), $series, hash('sha256', $apiKey));
        $this->em->persist($sensor);
        $this->em->flush();

        $location = $this->generateUrl('api_sensors_show', ['id' => $sensor->getId()]);

        // Jedyne miejsce, w którym klucz API jest ujawniany
        return $this->json($sensor->toArray() + ['apiKey' => $apiKey], Response::HTTP_CREATED, ['Location' => $location]);
    }

    #[Route('/{id}', name: 'api_sensors_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(#[MapEntity(message: 'Czujnik nie istnieje.')] Sensor $sensor): JsonResponse
    {
        return $this->json($sensor->toArray());
    }

    // Wyniki zebrane przez czujnik zostają (sensor_id → NULL w bazie).
    #[Route('/{id}', name: 'api_sensors_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(#[MapEntity(message: 'Czujnik nie istnieje.')] Sensor $sensor): Response
    {
        $this->em->remove($sensor);
        $this->em->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
