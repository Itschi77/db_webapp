<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class InvoiceAccountingHealthCheckService
{
    public function issues(CarbonImmutable $now): array
    {
        $issues = [];
        $db = DB::connection('sqlsrv_accountings');

        $this->checkLatest(
            $issues,
            'Switch-Accounting',
            fn () => $db->table('tblAccountingIntervall')->max('dateEnddatum'),
            $now->subMinutes(60),
        );
        $this->checkLatest(
            $issues,
            'IP-Accounting HERMES',
            fn () => $db->table('tblAccountingNetzeTageswerte')
                ->whereRaw("RTRIM(strQuelle) = 'hermes'")
                ->max('dateofRecordCreation'),
            $now->subMinutes(60),
        );
        $this->checkLatest(
            $issues,
            'Accounting-Monatssummen',
            fn () => $db->table('tblAnbindungAuswertung')->max('dateofRecordCreation'),
            $now->subHours(26),
        );

        $this->safeCheck($issues, 'Portbeschreibungen', function () use ($db, &$issues) {
            $count = $db->table('tblPort')
                ->where('boolDeaktiviert', 0)
                ->whereRaw(
                    "RTRIM(ISNULL(strIfDescrMust, '')) <> RTRIM(ISNULL(strIfDescrCurrent, ''))"
                )
                ->count();

            if ($count > 0) {
                $issues[] = $this->issue(
                    'Portbeschreibungen',
                    "{$count} aktive Port(s) weichen zwischen Soll- und Ist-Beschreibung ab.",
                );
            }
        });
        $this->safeCheck($issues, 'Port-Aktualisierung', function () use ($db, $now, &$issues) {
            $oldest = $db->table('tblPort')
                ->where('boolDeaktiviert', 0)
                ->min('dateIfDescrCurrent');
            $limit = $now->subHours(14);

            if ($oldest === null || CarbonImmutable::parse($oldest)->lt($limit)) {
                $value = $oldest
                    ? CarbonImmutable::parse($oldest)->format('d.m.Y H:i')
                    : 'kein Zeitstempel';
                $issues[] = $this->issue(
                    'Port-Aktualisierung',
                    "Älteste aktive Portbeschreibung: {$value}; erwartet nach {$limit->format('d.m.Y H:i')}.",
                );
            }
        });

        return $issues;
    }

    private function checkLatest(
        array &$issues,
        string $label,
        callable $query,
        CarbonImmutable $limit,
    ): void {
        $this->safeCheck($issues, $label, function () use (
            &$issues,
            $label,
            $query,
            $limit,
        ) {
            $latest = $query();

            if ($latest === null || CarbonImmutable::parse($latest)->lt($limit)) {
                $value = $latest
                    ? CarbonImmutable::parse($latest)->format('d.m.Y H:i')
                    : 'kein Datensatz';
                $issues[] = $this->issue(
                    $label,
                    "Letzter Wert: {$value}; erwartet nach {$limit->format('d.m.Y H:i')}.",
                );
            }
        });
    }

    private function safeCheck(array &$issues, string $label, callable $check): void
    {
        try {
            $check();
        } catch (QueryException $exception) {
            $permissionDenied = str_contains(
                mb_strtolower($exception->getMessage()),
                'berechtigung',
            );

            $issues[] = $this->issue(
                $permissionDenied ? 'SQL-Berechtigung' : 'Accounting-Prüfung',
                $permissionDenied
                    ? "Leserecht für die Prüfung „{$label}“ fehlt."
                    : "Prüfung „{$label}“ konnte nicht ausgeführt werden.",
            );
        }
    }

    private function issue(string $category, string $message): array
    {
        return [
            'order' => 0,
            'customer' => 'System',
            'category' => $category,
            'issue' => $message,
        ];
    }
}
