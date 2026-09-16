<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>{{ $id ? 'Anbindung '.$id : 'Neue Anbindung' }}</title><style>
body{font:14px "Segoe UI",Arial;background:#f4f6f8;color:#1f2937;padding:26px}.window{max-width:1150px;margin:auto;background:#fff;border:1px solid #d1d5db;border-radius:10px;padding:20px}.top{display:flex;justify-content:space-between}.grid{display:grid;grid-template-columns:190px 1fr;gap:8px 10px;align-items:center}.button{display:inline-block;padding:7px 12px;border:1px solid #777;background:#eee;color:#111;text-decoration:none;cursor:pointer}input,select,textarea{width:100%;box-sizing:border-box;padding:6px;border:1px solid #999;background:#fff}textarea{min-height:90px}.info{margin-top:12px;padding:10px;border:1px solid #aaa;background:#fff}.err{background:#fee;border:1px solid #b91c1c;padding:8px}.ok{background:#e8f6e8;border:1px solid #4b8b4b;padding:8px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #bbb;padding:5px}th{background:#ddd}</style></head><body><div class="window"><div class="top"><h2>{{ $id ? 'Anbindungs-ID '.$id : 'Neue Anbindung' }}</h2><a href="{{ route('frontend.switch','classic') }}">← Zur klassischen Ansicht</a></div>
@if(session('status'))<div class="ok">{{ session('status') }}</div>@endif @if($errors->any())<div class="err">{{ implode(' · ',$errors->all()) }}</div>@endif
<form method="post" action="{{ $id ? route('anbindungen.update',$id) : route('anbindungen.store') }}">@csrf @if($id)@method('PUT')@endif
<div class="grid"><label>AnbindungsReferenz</label><input id="refInput" type="number" min="0" name="intAnbindungReferenz" value="{{ old('intAnbindungReferenz',$anbindung->intAnbindungReferenz) }}" required>
<label>Art des Accountings</label><select id="typeSelect" name="intTyp" required>@foreach($types as $k=>$label)<option value="{{ $k }}" @selected((int)old('intTyp',$anbindung->intTyp)===$k)>{{ $k }} · {{ $label }}</option>@endforeach</select>
<label>ID der Auftragsposition</label><div><input type="number" min="1" name="intAuftragsPos" value="{{ old('intAuftragsPos',$anbindung->intAuftragsPos ?? $position->intID ?? '') }}" required>@if($position)<small>Auftrag {{ $auftrag->intAufNr }} · Kunde #{{ $kunde->intID }} {{ $kunde->strName }}</small>@endif</div>
<label>Abrechenbar?</label><label><input style="width:auto" type="checkbox" name="boolAbrechenbar" value="1" @checked(old('boolAbrechenbar',$anbindung->boolAbrechenbar))> Ja</label>
<label>Startdatum der Abrechnung</label><input type="datetime-local" step="1" name="dateAbrechenbarStart" value="{{ old('dateAbrechenbarStart',date('Y-m-d\\TH:i:s',strtotime($anbindung->dateAbrechenbarStart))) }}" required>
<label>Enddatum der Abrechnung</label><div><input type="datetime-local" step="1" name="dateAbrechenbarEnde" value="{{ old('dateAbrechenbarEnde',date('Y-m-d\\TH:i:s',strtotime($anbindung->dateAbrechenbarEnde))) }}" required><small>Bei Datum ohne Uhrzeit setzt der Server 23:59:59.</small></div>
<label>Kopie der Rechnungsinfo</label><textarea name="strKopieRechnungsinfo">{{ old('strKopieRechnungsinfo',$anbindung->strKopieRechnungsinfo ?? '') }}</textarea></div>
@foreach($referenceOptions as $typeKey=>$items)<datalist id="refs-{{ $typeKey }}">@foreach($items as $item)<option value="{{ $item['id'] }}">{{ $item['label'] }}</option>@endforeach</datalist>@endforeach
@if($referenzInfo)<div class="info"><strong>Informationen über das Accounting</strong><p><b>{{ $referenzInfo['title'] }}</b><br>{{ $referenzInfo['detail'] }}</p>@if(!empty($referenzInfo['rechnung']))<p>{!! nl2br(e(trim($referenzInfo['rechnung']))) !!}</p>@endif
@if(($referenzInfo['kind'] ?? '')==='fremd' && !empty($referenzInfo['rows']))<table><thead><tr><th>Monat</th><th>Jahr</th><th>MB rein</th><th>MB raus</th><th>Gesamt</th><th>Dauer sec</th><th>Rechnungsinfo</th></tr></thead><tbody>@foreach($referenzInfo['rows'] as $r)<tr><td>{{ $r->intMonat }}</td><td>{{ $r->intJahr }}</td><td>{{ $r->decMBin }}</td><td>{{ $r->decMBout }}</td><td>{{ $r->decGesamt }}</td><td>{{ $r->intVerbindungsdauerInSec }}</td><td>{{ $r->strrechnungsinfo }}</td></tr>@endforeach</tbody></table>@endif</div>@endif
<p><small>Technische Referenzdaten sind in diesem Formular nicht editierbar.</small></p>
@php
$technicalEditUrl = null;
if ($id && !empty($anbindung->intAnbindungReferenz)) {
    $technicalEditUrl = match ((int)$anbindung->intTyp) {
        1 => route('netze.edit', (int)$anbindung->intAnbindungReferenz),
        2 => route('ports.edit', (int)$anbindung->intAnbindungReferenz),
        3, 5 => route('dialins.edit', (int)$anbindung->intAnbindungReferenz),
        default => null,
    };
}
@endphp
<p><button class="button">Speichern</button> <a class="button" href="{{ route('anbindungen.index') }}">Schließen</a>@if($technicalEditUrl) <a class="button" href="{{ $technicalEditUrl }}">Technische Referenz öffnen</a>@endif @if($kunde) <a class="button" href="{{ route('kunden.show',$kunde->intID) }}">Kunde öffnen</a>@endif @if($kunde && $auftrag) <a class="button" href="{{ route('kunden.auftraege.show',[$kunde->intID,$auftrag->intAufNr]) }}">Auftrag öffnen</a>@endif</p></form>
<script>function refs(){const t=document.getElementById('typeSelect'),r=document.getElementById('refInput');if(!t||!r)return;const d=document.getElementById('refs-'+t.value);d?r.setAttribute('list',d.id):r.removeAttribute('list')}document.getElementById('typeSelect')?.addEventListener('change',refs);refs();</script></div><script src="{{ asset('js/db-window-manager.js') }}"></script></body></html>
