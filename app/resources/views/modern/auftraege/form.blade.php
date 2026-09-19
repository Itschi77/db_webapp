<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>{{ $id ? 'Auftrag '.$id.' bearbeiten' : 'Neuer Auftrag' }}</title><style>body{font:13px Arial;background:#d9d9d9;padding:12px}.window{max-width:980px;margin:auto;background:#efefef;border:1px solid #888;padding:14px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 24px}.field{display:grid;grid-template-columns:170px 1fr;gap:8px;align-items:center}input,select,textarea{width:100%;padding:5px;border:1px solid #999}textarea{min-height:70px}.flags{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:14px 0}.button{padding:7px 13px;border:1px solid #777;background:#eee;color:#111;text-decoration:none;cursor:pointer}.err{background:#fee;border:1px solid #b91c1c;padding:8px;margin-bottom:10px}</style></head><body>
<x-page-help title="Auftragsverwaltung">Hier suchst, prüfst und bearbeitest du Aufträge und Auftragspositionen. Änderungen an Positionen, Intervallen, Preisen oder Abrechnungsarten wirken auf spätere Rechnungen. Vor dem Speichern deshalb Abrechnungsart und Gültigkeit prüfen.</x-page-help>
<div class="window">
<div style="text-align:right;margin-bottom:10px"><a href="{{ route('frontend.switch', 'classic') }}">← Zur klassischen Ansicht</a></div>
<h2>{{ $id ? 'Auftrag '.$id.' bearbeiten' : 'Neuer Auftrag für '.$kunde->strName }}</h2>
@if($errors->any())<div class="err">{{ implode(' · ',$errors->all()) }}</div>@endif
<form method="post" action="{{ $id ? route('kunden.auftraege.update',[$kunde->intID,$id]) : route('kunden.auftraege.store',$kunde->intID) }}">@csrf @if($id) @method('PUT') @endif
<div class="grid">
<div class="field"><label>Kunde</label><input value="#{{ $kunde->intID }} {{ $kunde->strName }}" disabled></div>
<div class="field"><label>Rechnungsanschrift *</label><select name="intAnschriftID" required><option value="">Bitte wählen</option>@foreach($anschriften as $a)<option value="{{ $a->intID }}" @selected(old('intAnschriftID',$auftrag->intAnschriftID ?? null)==$a->intID)>{{ $a->strName }} · {{ $a->strStrasse }} {{ $a->strPLZ }} {{ $a->strOrt }}</option>@endforeach</select></div>
<div class="field"><label>Erfassungsdatum</label><input type="date" name="datErfassungsdatum" value="{{ old('datErfassungsdatum',isset($auftrag->datErfassungsdatum)?date('Y-m-d',strtotime($auftrag->datErfassungsdatum)):'') }}" required></div>
<div class="field"><label>Fakturieren ab</label><input type="date" name="datFakturierAb" value="{{ old('datFakturierAb',isset($auftrag->datFakturierAb)?date('Y-m-d',strtotime($auftrag->datFakturierAb)):'') }}" required></div>
<div class="field"><label>Storniert ab</label><input type="date" name="datStorniereAb" value="{{ old('datStorniereAb',!empty($auftrag->datStorniereAb)?date('Y-m-d',strtotime($auftrag->datStorniereAb)):'') }}"></div>
<div class="field"><label>Zahlungsbedingung</label><select name="intZahlungsbedingungID"><option value="">Keine</option>@foreach($zahlungsbedingungen as $z)<option value="{{ $z->intID }}" @selected(old('intZahlungsbedingungID',$auftrag->intZahlungsbedingungID ?? null)==$z->intID)>{{ $z->strBezeichnung }}</option>@endforeach</select></div>
<div class="field" style="grid-column:1/-1"><label>Beschreibung</label><textarea name="strBeschreibung">{{ old('strBeschreibung',$auftrag->strBeschreibung ?? '') }}</textarea></div>
<div class="field" style="grid-column:1/-1"><label>Abrechnungshinweis</label><input name="strAbrechnungshinweis" value="{{ old('strAbrechnungshinweis',$auftrag->strAbrechnungshinweis ?? '') }}"></div>
</div>
<div class="flags">
<label><input style="width:auto" type="checkbox" name="boolPapierrechnung" value="1" @checked(old('boolPapierrechnung',$auftrag->boolPapierrechnung ?? 0))> Papierrechnung</label>
<label><input style="width:auto" type="checkbox" name="boolEmailRechnung" value="1" @checked(old('boolEmailRechnung',$auftrag->boolEmailRechnung ?? 0))> E-Mail-Rechnung</label>
<label><input style="width:auto" type="checkbox" name="boolLastschriftErzeugen" value="1" @checked(old('boolLastschriftErzeugen',$auftrag->boolLastschriftErzeugen ?? 0))> Lastschrift</label>
<label><input style="width:auto" type="checkbox" name="boolDauerlastschrift" value="1" @checked(old('boolDauerlastschrift',$auftrag->boolDauerlastschrift ?? 0))> Dauerlastschrift</label>
<label><input style="width:auto" type="checkbox" name="boolRechnungstool" value="1" @checked(old('boolRechnungstool',$auftrag->boolRechnungstool ?? 0))> Rechnungstool</label>
<label><input style="width:auto" type="checkbox" name="boolVoraus" value="1" @checked(old('boolVoraus',$auftrag->boolVoraus ?? 0))> Vorausberechnung</label>
<label><input style="width:auto" type="checkbox" name="boolDomainrechnung" value="1" @checked(old('boolDomainrechnung',$auftrag->boolDomainrechnung ?? 0))> Domainrechnung</label>
<label><input style="width:auto" type="checkbox" name="boolSponsoring" value="1" @checked(old('boolSponsoring',$auftrag->boolSponsoring ?? 0))> Sponsoring</label>
<label><input style="width:auto" type="checkbox" name="boolEingefroren" value="1" @checked(old('boolEingefroren',$auftrag->boolEingefroren ?? 0))> Eingefroren</label>
</div>
<h3>Skonto</h3><div class="grid">
<div class="field"><label>Stufe 1 Tage</label><input type="number" min="0" name="intSkonto1Tage" value="{{ old('intSkonto1Tage',$auftrag->intSkonto1Tage ?? 0) }}" required></div><div class="field"><label>Stufe 1 %</label><input type="number" min="0" step="0.1" name="dezSkonto1Prozent" value="{{ old('dezSkonto1Prozent',$auftrag->dezSkonto1Prozent ?? 0) }}" required></div>
<div class="field"><label>Stufe 2 Tage</label><input type="number" min="0" name="intSkonto2Tage" value="{{ old('intSkonto2Tage',$auftrag->intSkonto2Tage ?? 0) }}" required></div><div class="field"><label>Stufe 2 %</label><input type="number" min="0" step="0.1" name="dezSkonto2Prozent" value="{{ old('dezSkonto2Prozent',$auftrag->dezSkonto2Prozent ?? 0) }}" required></div>
<div class="field"><label>Stufe 3 Tage</label><input type="number" min="0" name="intSkonto3Tage" value="{{ old('intSkonto3Tage',$auftrag->intSkonto3Tage ?? 0) }}" required></div><div class="field"><label>Stufe 3 %</label><input type="number" min="0" step="0.1" name="dezSkonto3Prozent" value="{{ old('dezSkonto3Prozent',$auftrag->dezSkonto3Prozent ?? 0) }}" required></div>
</div>
<p><button class="button" type="submit">Auftrag speichern</button> <a class="button" href="{{ $id ? route('kunden.auftraege.show',[$kunde->intID,$id]) : route('kunden.auftraege.index',$kunde->intID) }}">Abbrechen</a></p></form></div><script src="{{ asset('js/db-window-manager.js') }}"></script></body></html>
