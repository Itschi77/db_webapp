<!doctype html><html lang="de"><head><meta charset="utf-8"><title>Domain eintragen</title><style>
body{font:14px Segoe UI,Arial;background:#f4f6f8;padding:28px;color:#20242a}.card{background:#fff;border-radius:12px;padding:24px;max-width:820px;margin:auto;box-shadow:0 2px 12px #0001}.grid{display:grid;grid-template-columns:210px 1fr;gap:12px;align-items:center}input{padding:9px;border:1px solid #bbb;border-radius:6px}.buttons{display:flex;gap:10px;margin-top:20px}.button{padding:8px 14px;border:1px solid #aaa;border-radius:6px;background:#fff;color:#111;text-decoration:none;cursor:pointer}.err{padding:9px;border:1px solid #c88;background:#fff0f0;margin-bottom:14px}.hint{margin-top:18px;color:#555}</style></head><body><div class="card"><h2>Domain eintragen</h2>
@if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('domain-create.store') }}">@csrf<div class="grid">
<label>Domainname (ACE)</label><input name="domainname" maxlength="50" value="{{ old('domainname') }}" required autofocus>
<label>Domainname im Klartext</label><input name="klartextname" maxlength="50" value="{{ old('klartextname') }}">
<label>Kundennummer</label><input name="kundennummer" type="number" min="1" value="{{ old('kundennummer') }}" required>
<label>Auth Code</label><input name="authcode" type="password" maxlength="50" autocomplete="off">
</div><div class="buttons"><button class="button" type="submit">Domain anlegen</button><a class="button" href="{{ route('dashboard') }}">Abbrechen</a></div></form>
<p class="hint">SOA und beide Standard-NS-Einträge werden automatisch mit TTL 3600 angelegt. Danach öffnet sich direkt die DNS-Zonenpflege. DNSSEC und der historische Mail-Mechanismus bleiben bewusst außen vor.</p>
</div><script src="{{ asset('js/db-window-manager.js') }}"></script></body></html>
