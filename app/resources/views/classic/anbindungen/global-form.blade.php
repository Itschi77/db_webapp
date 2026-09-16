<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $id ? 'Anbindung '.$id : 'Neue Anbindung' }}</title>
<style>
body{font:12px Tahoma,Arial;background:#d9d9d9;margin:0;padding:8px;color:#111}.window{width:760px;max-width:calc(100vw - 20px);margin:auto;background:#f0f0f0;border:1px solid #777;padding:8px}.title{font-weight:bold;font-size:14px;margin:2px 0 10px}.main{display:grid;grid-template-columns:1fr 128px;gap:12px}.left{min-width:0}.right{display:flex;flex-direction:column;gap:8px;padding-top:74px}.right .button{text-align:center}.line{display:grid;grid-template-columns:155px 1fr;gap:8px;align-items:center;margin:6px 0}.line.short{grid-template-columns:155px 145px 1fr}.section{color:#0000cc;font-weight:bold;border-bottom:3px solid #0000cc;padding-bottom:3px;margin:8px 0}.button{display:inline-block;padding:6px 10px;border:1px solid #777;background:#efefef;color:#111;text-decoration:none;box-shadow:inset 1px 1px #fff;cursor:pointer}input,select,textarea{box-sizing:border-box;width:100%;font:12px Tahoma,Arial;border:1px solid #888;background:#fff;padding:3px}.readonly-filter{box-sizing:border-box;min-height:23px;padding:3px 20px 3px 4px;border:1px solid #888;background:#d7d7d7;position:relative;cursor:context-menu;white-space:pre-wrap;overflow-wrap:anywhere}.readonly-filter:after{content:'▾';position:absolute;right:5px;top:2px;color:#555}.readonly-filter:focus{outline:1px dotted #000;outline-offset:-3px}.subform{border:1px solid #777;background:#fff;padding:6px;min-height:130px;margin:5px 0}.subrow{display:grid;grid-template-columns:120px 1fr;gap:8px;margin:4px 0;align-items:center}.note{font-size:11px;margin:4px 0 8px}.order-note{text-align:center;font-size:11px;line-height:1.15;margin:0 0 8px}.plain-readonly{background:#fff!important;cursor:default}.invoice-copy{min-height:78px;padding-top:5px}.customerbox{display:grid;grid-template-columns:1fr 1fr;gap:10px;border:1px solid #aaa;padding:8px;margin-top:10px}.group{border:1px solid #aaa;padding:6px}.group-title{text-align:center;margin:-14px auto 6px;background:#f0f0f0;width:max-content;padding:0 5px}.nav{display:flex;align-items:center;gap:4px;margin-top:8px;border-top:1px solid #aaa;padding-top:5px}.nav a,.nav span{border:1px solid #aaa;background:#eee;padding:2px 5px;text-decoration:none;color:#111}.nav .plain{border:0;background:transparent}.ctx{display:none;position:fixed;z-index:9999;width:215px;background:#f4f4f4;border:1px solid #777;box-shadow:2px 2px 6px #0004;padding:3px}.ctx button{display:block;width:100%;border:0;background:transparent;text-align:left;padding:5px 8px;font:12px Tahoma;cursor:pointer}.ctx button:hover{background:#0a64ad;color:#fff}.ctx hr{border:0;border-top:1px solid #aaa;margin:3px}.ok,.err{padding:6px;margin-bottom:6px;border:1px solid}.ok{background:#e8f6e8;border-color:#4b8b4b}.err{background:#fee;border-color:#b91c1c}table{width:100%;border-collapse:collapse}th,td{border:1px solid #aaa;padding:3px}th{background:#ddd;text-align:left}
</style></head><body><div class="window">
<div class="title">Anbindungen</div>
@if(session('status'))<div class="ok">{{ session('status') }}</div>@endif
@if($errors->any())<div class="err">{{ implode(' · ',$errors->all()) }}</div>@endif
<form method="post" action="{{ $id ? route('anbindungen.update',$id) : route('anbindungen.store') }}">@csrf @if($id)@method('PUT')@endif
<div class="main"><div class="left">
<div class="line short"><b>Anbindungs-ID:</b>@if($id)<div class="readonly-filter" tabindex="0" data-filter-field="id" data-filter-value="{{ $id }}">{{ $id }}</div>@else<div class="readonly-filter">(neu)</div>@endif<span></span></div>
<div class="section">Informationen über das Accounting</div>
<div class="line"><span>AnbindungsReferenz:</span><input id="refInput" type="number" min="0" name="intAnbindungReferenz" value="{{ old('intAnbindungReferenz',$anbindung->intAnbindungReferenz) }}" required></div>
<div class="note" style="margin-left:163px">Diese ID muss aus der Netz-, Port- oder Dialin-Tabelle mit den entsprechenden Formularen herausgefunden und eingefügt werden.</div>
<div class="line"><span>Art des Accountings:</span><select id="typeSelect" name="intTyp" required>@foreach($types as $k=>$label)<option value="{{ $k }}" @selected((int)old('intTyp',$anbindung->intTyp)===$k)>{{ $label }}</option>@endforeach</select></div>
<div class="note">Die Informationen im Unterformular sind NICHT editierbar.</div>
@if($referenzInfo)
<div class="subform">
@if(!empty($referenzInfo['fields']))
@foreach($referenzInfo['fields'] as $fieldLabel=>$fieldValue)<div class="subrow"><span>{{ $fieldLabel }}</span><div class="readonly-filter" tabindex="0" data-filter-field="technical" data-filter-value="{{ trim((string)$fieldValue) }}">{{ $fieldValue }}</div></div>@endforeach
@else
<div class="subrow"><span>Information</span><div class="readonly-filter" tabindex="0" data-filter-field="technical" data-filter-value="{{ $referenzInfo['title'] }}">{{ $referenzInfo['title'] }}</div></div>
@endif
@if(($referenzInfo['kind'] ?? '')==='fremd' && !empty($referenzInfo['rows']))<table><thead><tr><th>Monat</th><th>Jahr</th><th>MB rein</th><th>MB raus</th><th>Gesamt</th><th>Dauer sec</th></tr></thead><tbody>@foreach($referenzInfo['rows'] as $r)<tr><td>{{ $r->intMonat }}</td><td>{{ $r->intJahr }}</td><td>{{ $r->decMBin }}</td><td>{{ $r->decMBout }}</td><td>{{ $r->decGesamt }}</td><td>{{ $r->intVerbindungsdauerInSec }}</td></tr>@endforeach</tbody></table>@endif
</div>
@else<div class="subform"><span style="color:#666">Keine Referenzinformation vorhanden.</span></div>@endif
<div class="line"><span>Abrechenbar?</span><label><input style="width:auto" type="checkbox" name="boolAbrechenbar" value="1" @checked(old('boolAbrechenbar',$anbindung->boolAbrechenbar))></label></div>
<div class="line"><span>Startdatum der Abrechnung:</span><input type="datetime-local" step="1" name="dateAbrechenbarStart" value="{{ old('dateAbrechenbarStart',date('Y-m-d\\TH:i:s',strtotime($anbindung->dateAbrechenbarStart))) }}" required></div>
<div class="line"><span>Enddatum der Abrechnung:</span><input type="datetime-local" step="1" name="dateAbrechenbarEnde" value="{{ old('dateAbrechenbarEnde',date('Y-m-d\\TH:i:s',strtotime($anbindung->dateAbrechenbarEnde))) }}" required></div>
<div class="line"><span>Kopie der Rechnungsinfo:</span><div class="readonly-filter invoice-copy" tabindex="0" data-filter-field="invoice_info" data-filter-value="{{ old('strKopieRechnungsinfo',$anbindung->strKopieRechnungsinfo ?? '') }}">{{ old('strKopieRechnungsinfo',$anbindung->strKopieRechnungsinfo ?? '') }}</div></div>
<input type="hidden" name="strKopieRechnungsinfo" value="{{ old('strKopieRechnungsinfo',$anbindung->strKopieRechnungsinfo ?? '') }}">
@if($id && $position && $auftrag && $kunde)
<div class="customerbox"><div class="group"><div class="group-title">Kundeninformationen des Auftrags</div>
<div class="subrow"><span>Kundennummer</span><div class="readonly-filter" tabindex="0" data-filter-field="customer_id" data-filter-value="{{ $kunde->intID }}">{{ $kunde->intID }}</div></div>
<div class="subrow"><span>Kundenname</span><div class="readonly-filter" tabindex="0" data-filter-field="customer_name" data-filter-value="{{ $kunde->strName }}">{{ $kunde->strName }}</div></div>
<div style="margin-top:6px"><a class="button" href="{{ route('kunden.show',$kunde->intID) }}">Kunde öffnen</a></div></div>
<div class="group"><div class="group-title">Auftrag</div>
<div class="order-note">Dieses Feld muss über die ID der Auftragsposition mit der Anbindung verknüpft werden, da sonst das Accounting nicht funktioniert.</div>
<div class="subrow"><span>ID der Auftragsposition:</span><input class="plain-readonly" type="text" value="{{ $position->intID }}" readonly></div>
<div style="text-align:right;margin:4px 0 7px"><a class="button" href="{{ route('kunden.auftraege.positionen.edit',[$kunde->intID,$auftrag->intAufNr,$position->intID]) }}">Position öffnen</a></div>
<div class="subrow"><span>ID des Auftrags:</span><div class="readonly-filter" tabindex="0" data-filter-field="order_id" data-filter-value="{{ $auftrag->intAufNr }}">{{ $auftrag->intAufNr }}</div></div>
<div style="text-align:right;margin-top:4px"><a class="button" href="{{ route('kunden.auftraege.show',[$kunde->intID,$auftrag->intAufNr]) }}">Auftrag öffnen</a></div></div></div>
<input type="hidden" name="intAuftragsPos" value="{{ $position->intID }}">
@else
<div class="line"><span>ID der Auftragsposition:</span><input type="number" min="1" name="intAuftragsPos" value="{{ old('intAuftragsPos',$anbindung->intAuftragsPos ?? '') }}" required></div>
@endif
</div>
<div class="right"><a class="button" href="{{ route('anbindungen.create') }}">Neue Anbindung</a><a class="button" href="{{ route('fremdaccounting.index') }}">Fremdaccountings</a><a class="button" href="{{ route('dashboard') }}">Schliessen</a><button class="button" type="submit">Speichern</button></div></div>
@if($nav)
@php $qbase=$nav['query']; @endphp
<div class="nav"><span class="plain">Datensatz:</span>
<a data-db-inline="1" href="{{ route('anbindungen.index',array_merge($qbase,['rid'=>$nav['first']])) }}">|◀</a>
@if($nav['prev'])<a data-db-inline="1" href="{{ route('anbindungen.index',array_merge($qbase,['rid'=>$nav['prev']])) }}">◀</a>@else<span>◀</span>@endif
<span>{{ $nav['index'] }} von {{ $nav['total'] }}</span>
@if($nav['next'])<a data-db-inline="1" href="{{ route('anbindungen.index',array_merge($qbase,['rid'=>$nav['next']])) }}">▶</a>@else<span>▶</span>@endif
<a data-db-inline="1" href="{{ route('anbindungen.index',array_merge($qbase,['rid'=>$nav['last']])) }}">▶|</a>
<span class="plain">{{ $fieldFilter ? '🔎 Gefiltert' : 'Ungefiltert' }}</span><span class="plain">Doppelklick auf graue Felder = Suchen/Filtern</span></div>
@endif
@foreach($referenceOptions as $typeKey=>$items)<datalist id="refs-{{ $typeKey }}">@foreach($items as $item)<option value="{{ $item['id'] }}">{{ $item['label'] }}</option>@endforeach</datalist>@endforeach
</form></div>
<div id="filterMenu" class="ctx" role="menu"><button data-op="equals">Gleich…</button><button data-op="not_equals">Nicht gleich…</button><hr><button data-op="starts">Beginnt mit…</button><button data-op="not_starts">Beginnt nicht mit…</button><button data-op="contains">Enthält…</button><button data-op="not_contains">Enthält nicht…</button><button data-op="ends">Endet mit…</button><button data-op="not_ends">Endet nicht mit…</button><hr><button data-clear="1">Alle Filter entfernen</button></div>
<script>
function refs(){const t=document.getElementById('typeSelect'),r=document.getElementById('refInput');if(!t||!r)return;const d=document.getElementById('refs-'+t.value);d?r.setAttribute('list',d.id):r.removeAttribute('list')} document.getElementById('typeSelect')?.addEventListener('change',refs);refs();
const menu=document.getElementById('filterMenu');let target=null;function openFilter(e,el){e.preventDefault();target=el;menu.style.display='block';menu.style.left=Math.min(e.clientX,window.innerWidth-230)+'px';menu.style.top=Math.min(e.clientY,window.innerHeight-285)+'px'}
document.querySelectorAll('.readonly-filter[data-filter-field]').forEach(el=>{el.addEventListener('contextmenu',e=>openFilter(e,el));el.addEventListener('dblclick',e=>openFilter(e,el))});document.addEventListener('click',e=>{if(!menu.contains(e.target))menu.style.display='none'});
menu.querySelectorAll('button[data-op]').forEach(btn=>btn.addEventListener('click',()=>{if(!target)return;const v=prompt('Filterwert:',target.dataset.filterValue||target.textContent.trim());if(v===null||v==='')return;const u=new URL('{{ route('anbindungen.index') }}',window.location.origin);u.searchParams.set('f_field',target.dataset.filterField);u.searchParams.set('f_op',btn.dataset.op);u.searchParams.set('f_value',v);window.location=u.toString()}));menu.querySelector('button[data-clear]').addEventListener('click',()=>window.location='{{ route('anbindungen.index') }}');
</script><script src="{{ asset('js/db-window-manager.js') }}"></script></body></html>
