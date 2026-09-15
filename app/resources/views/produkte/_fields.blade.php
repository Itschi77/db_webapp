@php
$staffeltypen=[0=>'Keine Staffeln',1=>'Abgestaffelte Grenzen',2=>'Linearstaffeln',3=>'Zeitaccounting',4=>'Bandbreitenabrechnung',5=>'Domain-Accounting',6=>'Bereichsstaffel'];
$typ=(int)old('intStaffeltyp',$produkt->intStaffeltyp ?? 0);
@endphp
<input type="hidden" id="domain-type-changed" name="domain_type_changed" value="0">
<div class="grid">
<div class="field wide"><label>Kürzel *</label><input name="strKuerzel" value="{{ old('strKuerzel',$produkt->strKuerzel ?? '') }}" required></div>
<div class="field wide"><label>Beschreibung *</label><textarea name="strBeschreibung" required>{{ old('strBeschreibung',$produkt->strBeschreibung ?? '') }}</textarea></div>
<div class="field"><label>Preis netto</label><input type="number" step="0.0001" name="fPreis" value="{{ old('fPreis',$produkt->fPreis ?? '') }}"></div>
<div class="field"><label>Steuer *</label><select name="intMwstSchluesselID" required>@foreach($listen['mwst'] as $x)<option value="{{ $x->intID }}" @selected(old('intMwstSchluesselID',$produkt->intMwstSchluesselID ?? null)==$x->intID)>{{ $x->strBezeichnung }}</option>@endforeach</select></div>
<div class="field"><label>Abrechnungsart *</label><select name="intAbrechnungsArt" required>@foreach($listen['abrechnungsarten'] as $x)<option value="{{ $x->intID }}" @selected(old('intAbrechnungsArt',$produkt->intAbrechnungsArt ?? null)==$x->intID)>#{{ $x->intID }} · {{ $x->strBezeichnung }}</option>@endforeach</select></div>
<div class="field"><label>Abgerechnet wird nach *</label><select id="menge" name="intMengenSchluessel" required>@foreach($listen['mengenschluessel'] as $x)<option value="{{ $x->intID }}" @selected(old('intMengenSchluessel',$produkt->intMengenSchluessel ?? null)==$x->intID)>{{ $x->strBezeichnung }}</option>@endforeach</select></div>
<div class="field"><label>DATEV-Auswahl</label><select name="intDatevBezeichnungsID"><option value="">-</option>@foreach($listen['datev'] as $x)<option value="{{ $x->intID }}" @selected(old('intDatevBezeichnungsID',$produkt->intDatevBezeichnungsID ?? null)==$x->intID)>{{ $x->strDatevKontierung }} · {{ $x->stDatevBezeichnung }}</option>@endforeach</select></div>
<div class="field"><label>Produktgruppe *</label><select name="intProduktGruppe" required>@foreach($listen['produktgruppen'] as $x)<option value="{{ $x->intID }}" @selected(old('intProduktGruppe',$produkt->intProduktGruppe ?? null)==$x->intID)>{{ $x->strBezeichnung }}</option>@endforeach</select></div>
<div class="field"><label>Staffeltyp *</label><select id="staffeltyp" name="intStaffeltyp" required>@foreach($staffeltypen as $id=>$name)<option value="{{ $id }}" @selected($typ===$id)>{{ $name }}</option>@endforeach</select></div>
<div class="field" id="staffel-field"><label id="staffel-label">Staffelgruppe</label><select id="staffelgruppe" name="intStaffelgruppeID" data-current="{{ old('intStaffelgruppeID',$produkt->intStaffelgruppeID ?? '') }}"></select></div>
<div class="field"><label>Max. Rabatt intern % *</label><input type="number" step="0.01" name="fMaxRabattFuerInternenVertrieb" value="{{ old('fMaxRabattFuerInternenVertrieb',$produkt->fMaxRabattFuerInternenVertrieb ?? 0) }}" required></div>
<div class="field"><label>Max. Rabatt extern % *</label><input type="number" step="0.01" name="fMaxRabattFuerExternenVertrieb" value="{{ old('fMaxRabattFuerExternenVertrieb',$produkt->fMaxRabattFuerExternenVertrieb ?? 0) }}" required></div>
<div class="checks wide"><label><input type="checkbox" name="boolIstAnbindung" value="1" @checked(old('boolIstAnbindung',$produkt->boolIstAnbindung ?? 0))> Accountingabhängig?</label><label><input type="checkbox" name="boolProduktInaktiv" value="1" @checked(old('boolProduktInaktiv',$produkt->boolProduktInaktiv ?? 0))> Produkt veraltet?</label></div>
</div>
<script>
const staffelData={
1:@json($listen['staffel1']->map(fn($x)=>['id'=>$x->intID,'text'=>$x->strBezeichnung])->values()),
2:@json($listen['staffel2']->map(fn($x)=>['id'=>$x->intID,'text'=>$x->strBezeichnung])->values()),
3:@json($listen['staffel3']->map(fn($x)=>['id'=>$x->intID,'text'=>$x->strTarifname])->values()),
5:@json($listen['staffel5']->map(fn($x)=>['id'=>$x->intID,'text'=>$x->strKonditionsName])->values()),
6:@json($listen['staffel6']->map(fn($x)=>['id'=>$x->intID,'text'=>$x->strBezeichnung])->values())};
const labels={1:'Staffelgruppe',2:'Linearstaffel',3:'Zeittarif',5:'Konditionen',6:'Bereichsstaffel'};
function refreshStaffel(initial=false){const typ=Number(document.getElementById('staffeltyp').value),field=document.getElementById('staffel-field'),sel=document.getElementById('staffelgruppe'),cur=sel.dataset.current; const list=staffelData[typ]; field.style.display=list?'grid':'none'; if(list){document.getElementById('staffel-label').textContent=labels[typ];sel.innerHTML='<option value="">-</option>'+list.map(x=>`<option value="${x.id}" ${String(x.id)===String(cur)?'selected':''}>${String(x.text??'')}</option>`).join('');}else{sel.innerHTML=cur?`<option value="${cur}" selected>${cur}</option>`:'<option value=""></option>';} if(!initial&&typ===5){document.getElementById('menge').value='1';document.getElementById('menge').disabled=true;document.getElementById('domain-type-changed').value='1';}else document.getElementById('menge').disabled=false;}
document.getElementById('staffeltyp').addEventListener('change',()=>{refreshStaffel(false);});refreshStaffel(true);
document.querySelector('form').addEventListener('submit',()=>{document.getElementById('menge').disabled=false;});
</script>
