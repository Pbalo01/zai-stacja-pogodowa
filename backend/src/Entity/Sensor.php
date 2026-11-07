<?php

namespace App\Entity;

use App\Repository\SensorRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SensorRepository::class)]
class Sensor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Series $series;

    #[ORM\Column(length: 64, unique: true)]
    private string $apiKeyHash;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastMeasurementAt = null;

    public function __construct(string $name, Series $series, string $apiKeyHash)
    {
        $this->name = $name;
        $this->series = $series;
        $this->apiKeyHash = $apiKeyHash;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSeries(): Series
    {
        return $this->series;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastMeasurementAt(): ?\DateTimeImmutable
    {
        return $this->lastMeasurementAt;
    }

    public function setLastMeasurementAt(\DateTimeImmutable $at): static
    {
        $this->lastMeasurementAt = $at;

        return $this;
    }

    // Jawna lista pól, żeby skrót klucza API nigdy nie trafił do odpowiedzi.
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'seriesId' => $this->series->getId(),
            'createdAt' => $this->createdAt->format(\DATE_ATOM),
            'lastMeasurementAt' => $this->lastMeasurementAt?->format(\DATE_ATOM),
        ];
    }
}
