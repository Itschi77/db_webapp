<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Auftragsverwaltung</title>
<style>
body{font:13px Arial;background:#d9d9d9;padding:12px}.window{max-width:1250px;margin:auto;background:#efefef;border:1px solid #888;padding:14px}
h1{font-size:22px}.toolbar{display:flex;gap:8px;margin:12px 0}.toolbar input{padding:6px;width:340px}.button{display:inline-block;padding:7px 12px;border:1px solid #777;background:#eee;color:#111;text-decoration:none}
table{width:100%;border-collapse:collapse;background:#fff}th,td{border:1px solid #aaa;padding:6px;vertical-align:top}th{background:#ddd;text-align:left}.muted{color:#666}
</style></head><body>
<x-page-help title="Auftragsverwaltung">Hier suchst, prüfst und bearbeitest du Aufträge und Auftragspositionen. Änderungen an Positionen, Intervallen, Preisen oder Abrechnungsarten wirken auf spätere Rechnungen. Vor dem Speichern deshalb Abrechnungsart und Gültigkeit prüfen.</x-page-help>
<div class="window">
<div style="text-align:right;margin-bottom:10px"><a href="{{ route('frontend.switch', 'modern') }}">Zum neuen Frontend wechseln →</a></div>
<h1>Auftragsverwaltung</h1>
<form class="toolbar" method="get"><input name="q" value="{{ request('q') }}" placeholder="Auftragsnr., Kundennr. oder Beschreibung"><button class="button" type="submit">Suchen</button><a class="button" href="{{ route('auftraege.index') }}">Zurücksetzen</a></form>
<table><thead><tr><th>Auftrag</th><th>Kunde</th><th>Beschreibung</th><th>Erfasst</th><th>Fakturier ab</th><th>Status</th></tr></thead><tbody>
@foreach($auftraege as $a)
<tr><td><a href="{{ route('kunden.auftraege.show',[$a->intKID,$a->intAufNr]) }}">{{ $a->intAufNr }}</a></td><td>{{ $a->intKID }}<br><span class="muted">{{ $kunden[$a->intKID]->strName ?? '' }}</span></td><td>{{ $a->strBeschreibung }}</td><td>{{ $a->datErfassungsdatum ? date('d.m.Y',strtotime($a->datErfassungsdatum)) : '' }}</td><td>{{ $a->datFakturierAb ? date('d.m.Y',strtotime($a->datFakturierAb)) : '' }}</td><td>
@if($a->datStorniereAb)
    Storniert ab {{ date('d.m.Y', strtotime($a->datStorniereAb)) }}
@elseif($a->boolEingefroren)
    Eingefroren
@else
    Aktiv
@endif
</td></tr>
@endforeach
</tbody></table>
<div style="margin-top:12px">{{ $auftraege->links() }}</div>
<p><a class="button" href="{{ route('dashboard') }}">← Hauptmenü</a></p>
</div><script src="{{ asset('js/db-window-manager.js') }}"></script></body></html>