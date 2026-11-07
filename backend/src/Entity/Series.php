<?php

namespace App\Entity;

use App\Repository\SeriesRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Seria pomiarowa, np. „Temperatura – salon”. Warunek min_value < max_value
 * pilnuje dodatkowo ograniczenie CHECK w bazie (patrz migracja).
 */
#[ORM\Entity(repositoryClass: SeriesRepository::class)]
class Series
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column]
    private float $minValue;

    #[ORM\Column]
    private float $maxValue;

    #[ORM\Column(length: 7)]
    private string $color;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $unit = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getMinValue(): float
    {
        return $this->minValue;
    }

    public function getMaxValue(): float
    {
        return $this->maxValue;
    }

    public function setRange(float $minValue, float $maxValue): static
    {
        $this->minValue = $minValue;
        $this->maxValue = $maxValue;

        return $this;
    }

    public function accepts(float $value): bool
    {
        return $value >= $this->minValue && $value <= $this->maxValue;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    /** Jawna lista pól zwracanych przez API. */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'minValue' => $this->minValue,
            'maxValue' => $this->maxValue,
            'color' => $this->color,
            'icon' => $this->icon,
            'unit' => $this->unit,
        ];
    }
}
