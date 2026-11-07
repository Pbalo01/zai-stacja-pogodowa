<?php

namespace App\Controller;

use App\Dto\SeriesInput;
use App\Entity\Series;
use App\Repository\MeasurementRepository;
use App\Repository\SeriesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/series')]
final class SeriesController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('', name: 'api_series_list', methods: ['GET'])]
    public function list(SeriesRepository $repository): JsonResponse
    {
        $series = $repository->findBy([], ['id' => 'ASC']);

        return $this->json(array_map(static fn (Series $s) => $s->toArray(), $series));
    }

    #[Route('', name: 'api_series_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(#[MapRequestPayload] SeriesInput $input): JsonResponse
    {
        $series = $this->apply(new Series(), $input);
        $this->em->persist($series);
        $this->em->flush();

        $location = $this->generateUrl('api_series_show', ['id' => $series->getId()]);

        return $this->json($series->toArray(), Response::HTTP_CREATED, ['Location' => $location]);
    }

    #[Route('/{id}', name: 'api_series_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(#[MapEntity(message: 'Seria nie istnieje.')] Series $series): JsonResponse
    {
        return $this->json($series->toArray());
    }

    #[Route('/{id}', name: 'api_series_update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(
        #[MapEntity(message: 'Seria nie istnieje.')] Series $series,
        #[MapRequestPayload] SeriesInput $input,
        MeasurementRepository $measurements,
    ): JsonResponse {
        // Nowy przedział nie może „wyrzucić” poza siebie zapisanych już wyników
        if ($measurements->existsOutsideRange($series, $input->minValue, $input->maxValue)) {
            throw new ConflictHttpException('Nowy przedział [minValue, maxValue] wyklucza wyniki zapisane już w serii.');
        }

        $this->apply($series, $input);
        $this->em->flush();

        return $this->json($series->toArray());
    }

    /** Wyniki i czujniki serii usuwa baza (ON DELETE CASCADE). */
    #[Route('/{id}', name: 'api_series_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(#[MapEntity(message: 'Seria nie istnieje.')] Series $series): Response
    {
        $this->em->remove($series);
        $this->em->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function apply(Series $series, SeriesInput $input): Series
    {
        return $series
            ->setName(trim($input->name))
            ->setRange($input->minValue, $input->maxValue)
            ->setColor($input->color)
            ->setIcon($input->icon)
            ->setUnit($input->unit);
    }
}
