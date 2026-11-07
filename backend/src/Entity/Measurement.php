<?php

namespace App\Entity;

use App\Repository\MeasurementRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Pojedynczy wynik pomiaru. Indeks (series_id, timestamp) obsługuje
 * typowe zapytanie „wyniki serii w przedziale czasu”.
 */
#[ORM\Entity(repositoryClass: MeasurementRepository::class)]
#[ORM\Index(name: 'idx_measurement_series_timestamp', columns: ['series_id', 'timestamp'])]
class Measurement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Series $series;

    /** Wynik zostaje po wyrejestrowaniu czujnika (ON DELETE SET NULL). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Sensor $sensor;

    #[ORM\Column]
    private float $value;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $timestamp;

    public function __construct(Series $series, ?Sensor $sensor, float $value, \DateTimeImmutable $timestamp)
    {
        $this->series = $series;
        $this->sensor = $sensor;
        $this->value = $value;
        $this->timestamp = $timestamp;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeries(): Series
    {
        return $this->series;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'seriesId' => $this->series->getId(),
            'sensorId' => $this->sensor?->getId(),
            'value' => $this->value,
            'timestamp' => $this->timestamp->format(\DATE_ATOM),
        ];
    }
}
