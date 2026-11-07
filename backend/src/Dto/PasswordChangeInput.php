<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class PasswordChangeInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Podaj bieżące hasło.')]
        public readonly ?string $currentPassword = null,

        #[Assert\NotBlank(message: 'Podaj nowe hasło.')]
        #[Assert\Length(min: 8, max: 4096, minMessage: 'Nowe hasło musi mieć co najmniej {{ limit }} znaków.')]
        public readonly ?string $newPassword = null,
    ) {
    }
}
