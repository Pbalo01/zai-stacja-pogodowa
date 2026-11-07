<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class SeriesInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Nazwa serii jest wymagana.')]
        #[Assert\Length(max: 100, maxMessage: 'Nazwa może mieć najwyżej {{ limit }} znaków.')]
        public readonly ?string $name = null,

        #[Assert\NotNull(message: 'Pole minValue jest wymagane.')]
        public readonly ?float $minValue = null,

        #[Assert\NotNull(message: 'Pole maxValue jest wymagane.')]
        #[Assert\GreaterThan(propertyPath: 'minValue', message: 'maxValue musi być większe niż minValue.')]
        public readonly ?float $maxValue = null,

        #[Assert\NotBlank(message: 'Kolor jest wymagany.')]
        #[Assert\Regex('/^#[0-9A-Fa-f]{6}$/', message: 'Kolor musi mieć format #RRGGBB.')]
        public readonly ?string $color = null,

        #[Assert\Length(max: 50, maxMessage: 'Nazwa ikony może mieć najwyżej {{ limit }} znaków.')]
        public readonly ?string $icon = null,

        #[Assert\Length(max: 20, maxMessage: 'Jednostka może mieć najwyżej {{ limit }} znaków.')]
        public readonly ?string $unit = null,
    ) {
    }
}
