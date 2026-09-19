<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Lastschriften bezahlt markieren</title>
<style>
*{box-sizing:border-box}body{margin:0;padding:12px;font:13px Arial;background:#d9d9d9;color:#111}.window{max-width:1180px;margin:auto;background:#efefef;border:1px solid #888;padding:14px}.top{display:flex;justify-content:space-between;gap:16px;align-items:center}.filter{display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin:12px 0}.field label{display:block;margin-bottom:3px}.field input{padding:6px;border:1px solid #999}.button{display:inline-block;padding:7px 12px;border:1px solid #777;background:#eee;color:#111;text-decoration:none;cursor:pointer}.button.danger{background:#f3e2e2;border-color:#9b5555}.summary{padding:9px;border:1px solid #aaa;background:#fff;margin:10px 0}.ok{padding:9px;border:1px solid #5a8f5a;background:#eaf7ea;margin:10px 0}.err{padding:9px;border:1px solid #a33;background:#fee;margin:10px 0}.table-wrap{overflow:auto;border:1px solid #999;background:#fff}table{width:100%;border-collapse:collapse;white-space:nowrap}th,td{padding:6px 7px;border-right:1px solid #ccc;border-bottom:1px solid #ddd;text-align:left}th{background:#e4e4e4;position:sticky;top:0}.num{text-align:right}.muted{color:#666}.confirm{margin:12px 0;padding:10px;border:1px solid #b78d33;background:#fff8df}.actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:12px}
</style>
</head><body>
<x-page-help title="Lastschriften bezahlt markieren">Hier werden Lastschrift-Rechnungen als bezahlt gekennzeichnet. Das verändert den Zahlungsstatus produktiver Rechnungen. Nur bestätigte Zahlungseingänge markieren und Auswahl vor dem Speichern sorgfältig prüfen.</x-page-help>
<div class="window">
<div class="top"><h2>Lastschriften bezahlt markieren</h2><a href="{{ route('frontend.switch','modern') }}">Zum neuen Frontend wechseln →</a></div>
@if(session('success'))<div class="ok">{{ session('success') }}</div>@endif
@if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
<form method="get" action="{{ route('lastschriften.index') }}" class="filter">
<div class="field"><label>Fällig ab</label><input type="date" name="von" value="{{ $von }}" required></div>
<div class="field"><label>Fällig bis</label><input type="date" name="bis" value="{{ $bis }}" required></div>
<button class="button" type="submit">Anzeigen</button>
</form>
<div class="summary"><strong>{{ $rows->count() }}</strong> offene Lastschrift(en) im Zeitraum · Gesamtsumme der zu buchenden Zahlbeträge: <strong>{{ number_format($summe,2,',','.') }} €</strong></div>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>Rechnung</th><th>Auftrag</th><th>Kunde</th><th>Fälligkeit</th><th>Zahlungsbed.</th><th class="num">Rechnungsbetrag</th><th class="num">Zahlbetrag</th></tr></thead><tbody>
@forelse($rows as $r)<tr><td>{{ $r->intID }}</td><td>@if($r->intRechNr)<a href="{{ route('rechnungen.show',$r->intID) }}">{{ $r->intRechNr }}</a>@else<span class="muted">–</span>@endif</td><td>{{ $r->intAufNr }}</td><td>{{ $r->strKundenNameAufRechnung ?: '–' }}</td><td>{{ \Carbon\Carbon::parse($r->datFaelligkeitsDatum)->format('d.m.Y') }}</td><td>{{ $r->intZahlungsbedingungID }}</td><td class="num">{{ number_format((float)$r->fRechnungsbetrag,2,',','.') }} €</td><td class="num"><strong>{{ number_format((float)$r->zahlbetrag,2,',','.') }} €</strong></td></tr>
@empty<tr><td colspan="8">Keine offenen Lastschriften im gewählten Zeitraum.</td></tr>@endforelse
</tbody></table></div>
@if($rows->isNotEmpty())
<form method="post" action="{{ route('lastschriften.mark-paid') }}" onsubmit="return confirm('Sollen wirklich alle angezeigten Lastschriften als bezahlt markiert werden?');">
@csrf<input type="hidden" name="von" value="{{ $von }}"><input type="hidden" name="bis" value="{{ $bis }}"><input type="hidden" name="confirm" value="1">
<div class="confirm">Beim Ausführen werden alle aktuell noch offenen Lastschriften im gewählten Zeitraum verarbeitet. Bezahldatum ist die jeweilige Fälligkeit. Die Skontostufen werden wie in Access 1 → 2 → 3 geprüft; die zuletzt gültige Stufe bestimmt den Zahlbetrag.</div>
<button class="button danger" type="submit">Alle angezeigten Lastschriften als bezahlt markieren</button>
</form>
@endif
<div class="actions"><a class="button" href="{{ route('dashboard') }}">← Hauptmenü</a></div>
</div><script src="{{ asset('js/db-window-manager.js') }}"></script></body></html>
