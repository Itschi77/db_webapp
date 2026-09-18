<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use RuntimeException;

class InvoiceDocumentEditService
{
    public function get(int $orderNumber): ?array
    {
        $draft = Session::get($this->key($orderNumber));
        return is_array($draft) ? $draft : null;
    }

    public function save(int $orderNumber, array $testRun, array $input, string $actor): array
    {
        $rows = $testRun['documentRows']->values();
        $descriptions = [];
        foreach ($rows as $index => $row) {
            $value = trim((string) ($input['descriptions'][$index] ?? $row->description));
            if ($value === '') {
                throw new RuntimeException('Beschreibung in Zeile '.($index + 1).' darf nicht leer sein.');
            }
            $descriptions[$index] = mb_substr($value, 0, 1000);
        }

        $note = trim((string) ($input['invoice_note'] ?? ''));
        $draft = [
            'order' => $orderNumber,
            'base_hash' => $this->fingerprint($testRun),
            'descriptions' => $descriptions,
            'invoice_note' => mb_substr($note, 0, 2000),
            'confirmed_at' => now()->toIso8601String(),
            'confirmed_by' => $actor,
        ];

        Session::put($this->key($orderNumber), $draft);

        $changes = [];
        foreach ($rows as $index => $row) {
            if ($descriptions[$index] !== (string) $row->description) {
                $changes[] = [
                    'line' => $index + 1,
                    'position' => (int) $row->positionId,
                    'before' => (string) $row->description,
                    'after' => $descriptions[$index],
                ];
            }
        }
        if ($note !== '') {
            $changes[] = ['field' => 'invoice_note', 'after' => $note];
        }

        Log::notice('Invoice document draft confirmed', [
            'order_number' => $orderNumber,
            'actor' => $actor,
            'base_hash' => $draft['base_hash'],
            'changes' => $changes,
        ]);

        return $draft;
    }

    public function clear(int $orderNumber, string $actor): void
    {
        Session::forget($this->key($orderNumber));
        Log::notice('Invoice document draft cleared', [
            'order_number' => $orderNumber,
            'actor' => $actor,
        ]);
    }

    public function apply(array $testRun, ?array $draft): array
    {
        if (!$draft) {
            $testRun['documentEdit'] = null;
            $testRun['invoiceNote'] = '';
            return $testRun;
        }
        if (!hash_equals((string) $draft['base_hash'], $this->fingerprint($testRun))) {
            throw new RuntimeException(
                'Der bestätigte Bearbeitungsstand ist veraltet, weil sich Berechnung, Betrag oder Steuer geändert haben.'
            );
        }

        $rows = $testRun['documentRows']->values();
        foreach ($rows as $index => $row) {
            if (array_key_exists($index, $draft['descriptions'])) {
                $row->description = (string) $draft['descriptions'][$index];
            }
        }

        $testRun['documentRows'] = $rows;
        $testRun['invoiceNote'] = (string) ($draft['invoice_note'] ?? '');
        $testRun['documentEdit'] = $draft;
        return $testRun;
    }

    public function fingerprint(array $testRun): string
    {
        $rows = $testRun['documentRows']->values()->map(fn ($row) => [
            'position' => (int) $row->positionId,
            'kind' => (string) $row->kind,
            'net' => round((float) $row->net, 8),
            'tax_rate' => round((float) $row->taxRate, 4),
            'tax' => round((float) $row->tax, 8),
        ])->all();

        return hash('sha256', json_encode([
            'rows' => $rows,
            'net' => round((float) $testRun['net'], 8),
            'tax' => round((float) $testRun['tax'], 8),
            'gross' => round((float) $testRun['gross'], 8),
        ], JSON_THROW_ON_ERROR));
    }

    private function key(int $orderNumber): string
    {
        return 'invoice_document_draft.'.$orderNumber;
    }
}
