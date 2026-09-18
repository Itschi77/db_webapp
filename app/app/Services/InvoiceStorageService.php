<?php

namespace App\Services;

use App\Models\AdminConnectionProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class InvoiceStorageService
{
    public const PROFILE_KEY = 'storage.midas_invoices';

    public function readiness(): array
    {
        try {
            $profile = $this->profile();
            $ready = ($profile->options['mode'] ?? null) === 'read-write'
                && is_dir($profile->host)
                && is_writable($profile->host)
                && trim((string) ($profile->options['unc_root'] ?? '')) !== '';
            return ['ready' => $ready, 'path' => $profile->host];
        } catch (RuntimeException $exception) {
            return ['ready' => false, 'path' => null, 'message' => $exception->getMessage()];
        }
    }

    public function storePdf(int $invoiceNumber, CarbonImmutable $invoiceDate, string $contents): array
    {
        $profile = $this->profile();
        if (($profile->options['mode'] ?? null) !== 'read-write') {
            throw new RuntimeException('Die konfigurierte Rechnungsablage ist nicht als schreibbar freigegeben.');
        }

        $relative = $this->relativePath($profile, $invoiceNumber, $invoiceDate);
        if (! Storage::disk('midas_invoices')->put($relative, $contents)) {
            throw new RuntimeException('Die PDF-Datei konnte nicht in der Rechnungsablage gespeichert werden.');
        }

        $uncRoot = rtrim((string) ($profile->options['unc_root'] ?? ''), "\\/");
        if ($uncRoot === '') {
            Storage::disk('midas_invoices')->delete($relative);
            throw new RuntimeException('Für die Rechnungsablage fehlt der UNC-Zielpfad.');
        }

        return [
            'relativePath' => $relative,
            'databasePath' => $uncRoot.'\\'.str_replace('/', '\\', $relative),
        ];
    }

    public function delete(string $relativePath): void
    {
        Storage::disk('midas_invoices')->delete($relativePath);
    }

    private function profile(): AdminConnectionProfile
    {
        $profile = AdminConnectionProfile::where('key', self::PROFILE_KEY)
            ->where('type', 'filesystem')
            ->where('active', true)
            ->where('last_test_status', 'ok')
            ->first();

        if (! $profile) {
            throw new RuntimeException('Die MIDAS-Rechnungsablage ist nicht aktiv oder nicht erfolgreich getestet.');
        }

        return $profile;
    }

    private function relativePath(
        AdminConnectionProfile $profile,
        int $invoiceNumber,
        CarbonImmutable $invoiceDate,
    ): string {
        $pattern = (string) ($profile->options['filename_pattern']
            ?? '{year}/Rechnungen/Papier/{invoice_number}.pdf');
        $relative = strtr($pattern, [
            '{year}' => (string) $invoiceDate->year,
            '{invoice_number}' => (string) $invoiceNumber,
        ]);
        $relative = ltrim(str_replace('\\', '/', $relative), '/');

        if ($relative === '' || str_contains($relative, '..') || ! str_ends_with(strtolower($relative), '.pdf')) {
            throw new RuntimeException('Das konfigurierte Dateinamenschema ist ungültig.');
        }
        if (! preg_match('#^[A-Za-z0-9._/-]+$#', $relative)) {
            throw new RuntimeException('Das Dateinamenschema enthält nicht erlaubte Zeichen.');
        }

        return $relative;
    }
}
