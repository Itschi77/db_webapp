<!doctype html><html lang="de"><head><meta charset="utf-8"><title>Domain eintragen</title><style>
*{box-sizing:border-box}body{margin:0;background:#d4d0c8;font:13px Tahoma,Arial;color:#111}.wrap{padding:22px;width:760px;max-width:100%}.box{border:1px solid #888;padding:16px;background:#d4d0c8}.grid{display:grid;grid-template-columns:210px 1fr;gap:8px 12px;align-items:center}input{width:100%;padding:5px;border:1px solid #777;background:#fff}.buttons{display:flex;gap:8px;margin-top:16px}.btn{border:1px solid #777;background:#eee;padding:6px 14px;color:#111;text-decoration:none;cursor:pointer}.err{background:#f5dede;border:1px solid #b77;padding:8px;margin-bottom:12px}.hint{margin-top:15px;font-size:11px;color:#444;line-height:1.45}</style></head><body><div class="wrap">
<div style="display:flex;justify-content:space-between;align-items:center"><h2>Domain eintragen</h2><a href="{{ route('frontend.switch','modern') }}">Zum neuen Frontend wechseln →</a></div>
@if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('domain-create.store') }}">@csrf
<div class="box"><div class="grid">
<label for="domainname">Domainname (ACE)</label><input id="domainname" name="domainname" maxlength="50" value="{{ old('domainname') }}" required autofocus>
<label for="klartextname">Domainname im Klartext</label><input id="klartextname" name="klartextname" maxlength="50" value="{{ old('klartextname') }}">
<label for="kundennummer">Kundennummer</label><input id="kundennummer" name="kundennummer" type="number" min="1" value="{{ old('kundennummer') }}" required>
<label for="authcode">Auth Code</label><input id="authcode" name="authcode" type="password" maxlength="50" autocomplete="off">
</div>
<div class="buttons"><button class="btn" type="submit">Anlegen</button><button class="btn" type="reset">Neu</button><a class="btn" href="{{ route('dashboard') }}" data-db-inline="1">Schliessen</a></div>
<div class="hint">Beim Anlegen werden automatisch SOA sowie zwei NS-Einträge mit TTL 3600 erzeugt. Anschließend wird die neue Zone in „Domain-Einträge bearbeiten“ geöffnet. Das DNS-Neueinlesen per historischem Outlook-Maschinenbefehl ist bewusst nicht Bestandteil dieses Schritts.</div>
</div></form></div><script src="{{ asset('js/db-window-manager.js') }}"></script></body></html>
