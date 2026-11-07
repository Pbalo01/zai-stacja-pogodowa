<?php

namespace App\Http;

/**
 * Ścisłe parsowanie znaczników czasu ISO 8601 ze strefą czasową,
 * np. 2026-10-15T08:30:00Z lub 2026-10-15T10:30:00.123+02:00.
 * Konstruktor DateTimeImmutable przyjąłby też „yesterday” czy „now” – tu nie.
 */
final class IsoDateTime
{
    private const PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:?\d{2})$/i';

    public static function parse(string $value): ?\DateTimeImmutable
    {
        if (!preg_match(self::PATTERN, $value)) {
            return null;
        }

        try {
            $date = new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }

        // Odrzucamy daty „przekręcone” przez PHP, np. 2026-02-31 → 3 marca
        return substr($value, 0, 10) === $date->format('Y-m-d') ? $date : null;
    }
}
