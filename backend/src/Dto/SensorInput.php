<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Treść POST /api/sensors. */
final class SensorInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Nazwa czujnika jest wymagana.')]
        #[Assert\Length(max: 100, maxMessage: 'Nazwa może mieć najwyżej {{ limit }} znaków.')]
        public readonly ?string $name = null,

        #[Assert\NotNull(message: 'Pole seriesId jest wymagane.')]
        public readonly ?int $seriesId = null,
    ) {
    }
}
