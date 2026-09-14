<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Auftragsverwaltung</title>
<style>*{box-sizing:border-box}body{margin:0;font:14px Segoe UI,Arial;background:#f4f6f8;color:#1f2937}.page{max-width:1250px;margin:auto;padding:32px}.card{background:#fff;border-radius:12px;box-shadow:0 2px 8px #0001;padding:24px}.toolbar{display:flex;gap:8px;margin:16px 0}.toolbar input{flex:1;max-width:420px;padding:10px;border:1px solid #cbd5e1;border-radius:7px}.button{padding:10px 14px;border:0;border-radius:7px;background:#0369a1;color:#fff;text-decoration:none}table{width:100%;border-collapse:collapse}th,td{padding:10px 8px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}th{font-size:12px;text-transform:uppercase;color:#64748b}a{color:#0369a1;text-decoration:none}.muted{color:#64748b}</style></head><body><div class="page">
<div style="text-align:right;margin-bottom:10px"><a href="{{ route('frontend.switch', 'classic') }}">← Zur klassischen Ansicht</a></div>
<a href="{{ route('dashboard') }}">← Hauptmenü</a><h1>Auftragsverwaltung</h1><div class="card">
<form class="toolbar" method="get"><input name="q" value="{{ request('q') }}" placeholder="Auftragsnr., Kundennr. oder Beschreibung"><button class="button" type="submit">Suchen</button><a class="button" href="{{ route('auftraege.index') }}">Zurücksetzen</a></form>
<table><thead><tr><th>Auftrag</th><th>Kunde</th><th>Beschreibung</th><th>Erfasst</th><th>Fakturier ab</th><th>Status</th></tr></thead><tbody>
@foreach($auftraege as $a)
<tr><td><a href="{{ route('kunden.auftraege.show',[$a->intKID,$a->intAufNr]) }}"><strong>{{ $a->intAufNr }}</strong></a></td><td>{{ $a->intKID }}<br><span class="muted">{{ $kunden[$a->intKID]->strName ?? '' }}</span></td><td>{{ $a->strBeschreibung }}</td><td>{{ $a->datErfassungsdatum ? date('d.m.Y',strtotime($a->datErfassungsdatum)) : '' }}</td><td>{{ $a->datFakturierAb ? date('d.m.Y',strtotime($a->datFakturierAb)) : '' }}</td><td>
@if($a->datStorniereAb)
    Storniert ab {{ date('d.m.Y', strtotime($a->datStorniereAb)) }}
@elseif($a->boolEingefroren)
    Eingefroren
@else
    Aktiv
@endif
</td></tr>
@endforeach
</tbody></table><div style="margin-top:16px">{{ $auftraege->links() }}</div>
</div></div></body></html>