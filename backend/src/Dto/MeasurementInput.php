<?php

namespace App\Dto;

use App\Http\IsoDateTime;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

// Seria wynika z klucza czujnika, dlatego przedział min/max sprawdza kontroler.
final class MeasurementInput
{
    // Dopuszczalne „wyprzedzenie” zegara czujnika względem serwera.
    public const MAX_CLOCK_SKEW = '+5 minutes';

    public function __construct(
        #[Assert\NotNull(message: 'Pole value jest wymagane.')]
        public readonly ?float $value = null,

        public readonly ?string $timestamp = null,
    ) {
    }

    // Brak timestamp w treści = bieżący czas serwera.
    public function getTimestamp(): \DateTimeImmutable
    {
        return null === $this->timestamp ? new \DateTimeImmutable() : IsoDateTime::parse($this->timestamp);
    }

    #[Assert\Callback]
    public function validateTimestamp(ExecutionContextInterface $context): void
    {
        if (null === $this->timestamp) {
            return;
        }

        $date = IsoDateTime::parse($this->timestamp);
        if (null === $date) {
            $context->buildViolation('Znacznik czasu musi być w formacie ISO 8601 ze strefą, np. 2026-10-15T08:30:00Z.')
                ->atPath('timestamp')->addViolation();
        } elseif ($date > new \DateTimeImmutable(self::MAX_CLOCK_SKEW)) {
            $context->buildViolation('Znacznik czasu nie może być późniejszy niż 5 minut od bieżącego czasu serwera.')
                ->atPath('timestamp')->addViolation();
        }
    }
}
