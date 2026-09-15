<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingBerichtController extends Controller
{
    private const VARIANTS = ['ohne-zusatzinfos', 'mit-zusatzsumme', 'ohne-zusatzsumme'];

    public function index(Request $request)
    {
        $variant = $request->input('variante', 'ohne-zusatzinfos');
        abort_unless(in_array($variant, self::VARIANTS, true), 404);

        $monat = (int) $request->input('monat', now()->month);
        $jahr = (int) $request->input('jahr', now()->year);
        $kundenId = $request->filled('kunde') ? (int) $request->input('kunde') : null;
        $rows = collect();
        $kunde = null;
        $totals = ['in' => 0.0, 'out' => 0.0, 'gesamt' => 0.0];

        if ($kundenId) {
            validator(['monat'=>$monat,'jahr'=>$jahr,'kunde'=>$kundenId], [
                'monat'=>['integer','between:1,12'], 'jahr'=>['integer','between:1990,2100'], 'kunde'=>['integer','min:1'],
            ])->validate();
            $kunde = DB::connection('sqlsrv_topsnetdb_safe')->table('tblKunde')->where('intID', $kundenId)
                ->select('intID','strName','strAnrede','strStrasse','strPLZ','strOrt')->first();
            $rows = $this->loadRows($variant, $kundenId, $monat, $jahr);
            $seconds = $this->loadSeconds($monat, $jahr);
            foreach ($rows as $row) {
                $row->sumsekunden = $seconds[$row->intAnbID] ?? null;
                $row->zusatzinfo = $this->zusatzinfo($row);
            }
            $sumRows = $variant === 'ohne-zusatzsumme'
                ? $rows->filter(fn($r) => (bool)$r->boolAbrechenbar)
                : $rows;
            $totals = [
                'in'=>(float)$sumRows->sum('decMBin'), 'out'=>(float)$sumRows->sum('decMBout'), 'gesamt'=>(float)$sumRows->sum('decGesamt'),
            ];
        }

        $titles = [
            'ohne-zusatzinfos'=>'Accountings ohne Zusatzinfos',
            'mit-zusatzsumme'=>'Accountings mit Zusatzinfos und Zusatzsumme',
            'ohne-zusatzsumme'=>'Accountings mit Zusatzinfos ohne Zusatzsumme',
        ];
        $mode = session('frontend_mode', 'classic');
        return view($mode.'.accounting-berichte.index', compact('variant','monat','jahr','kundenId','rows','kunde','totals','titles'));
    }

    private function loadRows(string $variant, int $kundenId, int $monat, int $jahr)
    {
        $db = DB::connection('sqlsrv_accountings');
        $q = $db->table('tblAnbindungen as an')
            ->join('tblAnbindungAuswertung as au', 'an.intID', '=', 'au.intAnbindungID');

        if ($variant === 'ohne-zusatzinfos') {
            $q->join('tblAuftragPos as p', 'p.intID', '=', 'an.intAuftragsPos')
              ->join('tblAuftrag as a', 'a.intAufNr', '=', 'p.intAufNr')
              ->where('a.intKID', $kundenId)
              ->where('an.boolAbrechenbar', '<>', 0);
        } else {
            $q->where('an.intKID', $kundenId);
        }

        return $q->where('au.intMonat', $monat)->where('au.intJahr', $jahr)
            ->select('au.decMBin','au.decMBout','au.decGesamt','au.intMonat','au.intJahr','au.strrechnungsinfo',
                'an.intID as intAnbID','an.boolAbrechenbar','an.dateAbrechenbarStart','an.dateAbrechenbarEnde','an.intTyp','an.intAnbindungReferenz')
            ->orderByDesc('au.decGesamt')->get();
    }

    private function loadSeconds(int $monat, int $jahr): array
    {
        $start = Carbon::create($jahr, $monat, 1)->startOfDay();
        $end = $start->copy()->addMonth();
        return DB::connection('sqlsrv_accountings')->table('tblAnbindungen as an')
            ->join('tblAnbindungenDialinWerte as dw', 'an.intAnbindungReferenz', '=', 'dw.IntDialinID')
            ->where('an.intTyp', 3)->where('dw.datEnd', '>=', $start)->where('dw.datEnd', '<', $end)
            ->groupBy('an.intID')->selectRaw('an.intID, SUM(dw.intVerbindungsdauerInSec) AS sekunden')
            ->pluck('sekunden','intID')->map(fn($v)=>(int)$v)->all();
    }

    private function zusatzinfo(object $row): string
    {
        $parts = [];
        if ($row->dateAbrechenbarStart && Carbon::parse($row->dateAbrechenbarStart)->format('Y-m-d') !== '1990-01-01')
            $parts[] = '(dieser Dienst wird seit dem '.Carbon::parse($row->dateAbrechenbarStart)->format('d.m.Y').' in Anspruch genommen)';
        if ($row->dateAbrechenbarEnde && Carbon::parse($row->dateAbrechenbarEnde)->format('Y-m-d') !== '2019-12-31')
            $parts[] = '(dieser Dienst wurde bis zum '.Carbon::parse($row->dateAbrechenbarEnde)->format('d.m.Y').' in Anspruch genommen)';
        if ((int)$row->intTyp === 3) {
            $s = (int)($row->sumsekunden ?? 0); $h = intdiv($s,3600); $m = intdiv($s%3600,60); $sec = $s%60;
            $parts[] = sprintf('Verbindungszeit: %d:%02d:%02d Stunden (%d Sekunden)', $h,$m,$sec,$s);
        }
        return implode(' ', $parts);
    }
}
