<?php

namespace App\Services;

use Illuminate\Support\Collection;

class InvoiceAddressPlausibilityService
{
    public function issues(?object $address): Collection
    {
        $issues = collect();

        if (! $address) {
            return $issues;
        }

        $postalCode = trim((string) ($address->strPLZ ?? ''));
        $city = trim((string) ($address->strOrt ?? ''));

        if ($postalCode === '' || $city === '') {
            return $issues;
        }

        $cityCompact = preg_replace('/\s+/', '', $city) ?? $city;
        $postalCompact = preg_replace('/\s+/', '', $postalCode) ?? $postalCode;

        $cityIsNumeric = preg_match('/^\d+$/', $cityCompact) === 1;
        $postalContainsLetters = preg_match('/\p{L}/u', $postalCompact) === 1;

        if ($cityIsNumeric && $postalContainsLetters) {
            $issues->push(
                'Rechnungsanschrift wirkt vertauscht: Im Feld PLZ steht Text, im Feld Ort nur eine Zahl. Bitte PLZ und Ort im Kundenstamm bzw. in der Rechnungsanschrift prüfen.'
            );
        }

        return $issues;
    }
}
