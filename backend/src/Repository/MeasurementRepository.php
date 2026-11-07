<?php

namespace App\Repository;

use App\Entity\Measurement;
use App\Entity\Series;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Measurement>
 */
class MeasurementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Measurement::class);
    }

    /**
     * Przedział [from, to] jest domknięty.
     *
     * @param int[] $seriesIds pusta tablica = wszystkie serie
     *
     * @return Measurement[]
     */
    public function search(array $seriesIds, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to, bool $descending, int $limit): array
    {
        $direction = $descending ? 'DESC' : 'ASC';
        $qb = $this->createQueryBuilder('m')
            ->orderBy('m.timestamp', $direction)
            ->addOrderBy('m.id', $direction)
            ->setMaxResults($limit);

        if ($seriesIds) {
            $qb->andWhere('IDENTITY(m.series) IN (:series)')->setParameter('series', $seriesIds);
        }
        if ($from) {
            $qb->andWhere('m.timestamp >= :from')->setParameter('from', $from, 'datetimetz_immutable');
        }
        if ($to) {
            $qb->andWhere('m.timestamp <= :to')->setParameter('to', $to, 'datetimetz_immutable');
        }

        return $qb->getQuery()->getResult();
    }

    public function existsOutsideRange(Series $series, float $min, float $max): bool
    {
        $count = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.series = :series')
            ->andWhere('m.value < :min OR m.value > :max')
            ->setParameter('series', $series)
            ->setParameter('min', $min)
            ->setParameter('max', $max)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
