<?php

namespace App\Services;

use App\Models\AdminConnectionProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class InvoiceStorageService
{
    public const PROFILE_KEY = 'storage.rechnungen';

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
        $this->ensureSharedPermissions($profile, $relative);
        if (! Storage::disk('rechnungen')->put($relative, $contents)) {
            throw new RuntimeException('Die PDF-Datei konnte nicht in der Rechnungsablage gespeichert werden.');
        }
        @chmod(rtrim($profile->host, '/').'/'.$relative, 0666);

        $uncRoot = rtrim((string) ($profile->options['unc_root'] ?? ''), "\\/");
        if ($uncRoot === '') {
            Storage::disk('rechnungen')->delete($relative);
            throw new RuntimeException('Für die Rechnungsablage fehlt der UNC-Zielpfad.');
        }

        return [
            'relativePath' => $relative,
            'databasePath' => $uncRoot.'\\'.str_replace('/', '\\', $relative),
        ];
    }

    public function storeXml(int $invoiceNumber, CarbonImmutable $invoiceDate, string $contents): array
    {
        $profile = $this->profile();
        if (($profile->options['mode'] ?? null) !== 'read-write') {
            throw new RuntimeException('Die konfigurierte Rechnungsablage ist nicht als schreibbar freigegeben.');
        }

        $pdfRelative = $this->relativePath($profile, $invoiceNumber, $invoiceDate);
        $relative = preg_replace('/\\.pdf$/i', '.xrechnung.xml', $pdfRelative);
        if (!is_string($relative) || $relative === $pdfRelative) {
            throw new RuntimeException('Der XML-Ablagepfad konnte nicht abgeleitet werden.');
        }
        $this->ensureSharedPermissions($profile, $relative);
        if (! Storage::disk('rechnungen')->put($relative, $contents)) {
            throw new RuntimeException('Die XRechnung-XML konnte nicht in der Rechnungsablage gespeichert werden.');
        }
        @chmod(rtrim($profile->host, '/').'/'.$relative, 0666);

        $uncRoot = rtrim((string) ($profile->options['unc_root'] ?? ''), "\\/");
        return [
            'relativePath' => $relative,
            'databasePath' => $uncRoot.'\\'.str_replace('/', '\\', $relative),
        ];
    }

    public function delete(string $relativePath): void
    {
        Storage::disk('rechnungen')->delete($relativePath);
    }

    private function ensureSharedPermissions(AdminConnectionProfile $profile, string $relativePath): void
    {
        $directory = dirname(rtrim($profile->host, '/').'/'.$relativePath);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Der Zielordner für die Rechnungsablage konnte nicht angelegt werden.');
        }
        @chmod($directory, 0777);
    }

    private function profile(): AdminConnectionProfile
    {
        $profile = AdminConnectionProfile::where('key', self::PROFILE_KEY)
            ->where('type', 'filesystem')
            ->where('active', true)
            ->where('last_test_status', 'ok')
            ->first();

        if (! $profile) {
            throw new RuntimeException('Die Janus-Rechnungsablage ist nicht aktiv oder nicht erfolgreich getestet.');
        }

        return $profile;
    }

    private function relativePath(
        AdminConnectionProfile $profile,
        int $invoiceNumber,
        CarbonImmutable $invoiceDate,
    ): string {
        $pattern = (string) ($profile->options['filename_pattern']
            ?? '{year}/{invoice_number}.pdf');
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
