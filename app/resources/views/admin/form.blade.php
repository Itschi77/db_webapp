<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verbindung {{ $profile->exists?'bearbeiten':'anlegen' }}</title>
<style>
*{box-sizing:border-box}body{font-family:system-ui,Segoe UI,Arial;background:#f3f6fa;color:#172033;margin:0}
.page{max-width:980px;margin:25px auto;padding:0 18px}.card{background:white;border:1px solid #dfe5ee;border-radius:13px;padding:22px;box-shadow:0 3px 14px #1f29370c}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field{display:flex;flex-direction:column;gap:5px}.wide{grid-column:1/-1}
input,select,textarea{font:inherit;padding:10px;border:1px solid #ccd3df;border-radius:8px}textarea{min-height:100px}
.btn{border:0;border-radius:8px;background:#1f4f8a;color:#fff;padding:10px 16px;font-weight:700;cursor:pointer}
.errors{background:#fff0ee;padding:10px;border-radius:8px;margin-bottom:12px}.muted{color:#667085;font-size:12px}
.form-section{grid-column:1/-1;border:1px solid #e1e7ef;border-radius:11px;padding:16px;background:#f9fbfd}
.form-section h2{font-size:17px;margin:0 0 4px}.form-section .section-note{margin:0 0 14px;color:#667085;font-size:13px}
.section-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.hidden{display:none}
.help-tip{position:relative;display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:#eef4fb;color:#1f4f8a;font-weight:800;font-size:13px;cursor:help;outline:none;flex:0 0 auto}.help-tip:hover,.help-tip:focus{background:#dbe9f8}.help-tip-icon{line-height:1}.help-tip-popup{display:none;position:absolute;right:0;top:30px;z-index:50;width:min(360px,80vw);padding:11px 12px;border:1px solid #cfd9e6;border-radius:9px;background:#fff;color:#253247;box-shadow:0 8px 24px #0002;font-size:12px;font-weight:400;line-height:1.45;text-align:left}.help-tip-popup strong{display:block;margin-bottom:5px;color:#172033;font-size:13px}.help-tip-popup span{display:block}.help-tip:hover .help-tip-popup,.help-tip:focus .help-tip-popup,.help-tip:focus-within .help-tip-popup{display:block}.form-section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
@media(max-width:700px){.grid,.section-grid{grid-template-columns:1fr}.wide{grid-column:auto}}
</style>
</head>
<body><main class="page"><p><a href="{{ route('admin.index') }}">← Administration</a></p>
<section class="card"><div style="display:flex;justify-content:space-between;align-items:center;gap:12px"><h1>Verbindung {{ $profile->exists?'bearbeiten':'anlegen' }}</h1><x-help-tip title="Verbindungsprofil">Ein Profil beschreibt, wie Janus ein externes System erreicht. Änderungen wirken nach dem Speichern auf spätere Tests und Zugriffe. Passwörter werden nicht wieder angezeigt; leer lassen behält beim Bearbeiten das vorhandene Secret.</x-help-tip></div>
@if($errors->any())<div class="errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="post" action="{{ $profile->exists?route('admin.connections.update',$profile):route('admin.connections.store') }}">@csrf @if($profile->exists)@method('put')@endif
<div class="grid">
<section class="form-section"><div class="form-section-head"><div><h2>Grunddaten</h2><p class="section-note">Bezeichnung, technischer Schlüssel und Verbindungsart.</p></div><x-help-tip title="Grunddaten">Der Name ist nur die sichtbare Bezeichnung. Der technische Schlüssel wird von der Anwendung verwendet und sollte nach produktiver Nutzung nicht leichtfertig geändert werden. „Profil aktiv verwenden“ entscheidet, ob Janus diese Verbindung berücksichtigt.</x-help-tip></div><div class="section-grid">
<label class="field">Name<input name="name" required value="{{ old('name',$profile->name) }}"></label>
<label class="field">Technischer Schlüssel<input name="key" required pattern="[a-z0-9._-]+" value="{{ old('key',$profile->key) }}"><span class="muted">z. B. sql.accountings, smtp.primary oder storage.midas</span></label>
<label class="field">Typ<select id="profile-type" name="type" required>@foreach(['sqlserver'=>'SQL Server','postgres'=>'PostgreSQL','smtp'=>'SMTP','filesystem'=>'Dateisystem','http'=>'HTTP/HTTPS'] as $value=>$label)<option value="{{ $value }}" @selected(old('type',$profile->type)===$value)>{{ $label }}</option>@endforeach</select></label>
<label class="field">Status<label><input style="width:auto" type="checkbox" name="active" value="1" @checked(old('active',$profile->active))> Profil aktiv verwenden</label></label>
</div></section>
<section class="form-section"><div class="form-section-head"><div><h2>Server und Zugang</h2><p class="section-note">Zielsystem und gegebenenfalls Anmeldedaten.</p></div><x-help-tip title="Server und Zugang">Host kann Servername, IP, URL oder lokaler Mount sein. Port und Datenbank werden nur für passende Typen genutzt. Beim Bearbeiten bleibt ein bestehendes Passwort erhalten, wenn das Passwortfeld leer bleibt.</x-help-tip></div><div class="section-grid">
<label class="field wide">Host, URL oder Verzeichnispfad<input name="host" value="{{ old('host',$profile->host) }}" placeholder="mail.example.de, 10.79.160.38, https://… oder /mnt/…"></label>
<label class="field" data-types="sqlserver postgres smtp">Port<input type="number" min="1" max="65535" name="port" value="{{ old('port',$profile->port) }}"></label>
<label class="field" data-types="sqlserver postgres">Datenbank<input name="database" value="{{ old('database',$profile->database) }}"></label>
<label class="field" data-types="sqlserver postgres smtp">Benutzername / Postfach<input name="username" autocomplete="off" value="{{ old('username',$profile->username) }}"></label>
<label class="field" data-types="sqlserver postgres smtp">Passwort / Secret<input type="password" name="secret" autocomplete="new-password" placeholder="{{ $profile->exists?'leer lassen = unverändert':'' }}"></label>
</div></section>
<section class="form-section" data-types="smtp"><div class="form-section-head"><div><h2>SMTP-Mailversand</h2><p class="section-note">Postfach, Sicherheit und Absender für Test- und Rechnungs-E-Mails.</p></div><x-help-tip title="SMTP-Mailversand">Hier werden Verschlüsselung, Anmeldung und Absender definiert. Nach dem Speichern im Adminbereich zuerst „Erreichbarkeit testen“ und danach bei Bedarf eine echte Testmail senden. Nur smtp.primary ist für den späteren Rechnungsversand vorgesehen.</x-help-tip></div><div class="section-grid">
<label class="field">Verschlüsselung<select name="smtp_encryption"><option value="starttls" @selected(old('smtp_encryption',data_get($profile->options,'encryption','starttls'))==='starttls')>STARTTLS</option><option value="smtps" @selected(old('smtp_encryption',data_get($profile->options,'encryption'))==='smtps')>SMTPS / implizites TLS</option><option value="none" @selected(old('smtp_encryption',data_get($profile->options,'encryption'))==='none')>Keine</option></select></label>
<label class="field">Authentifizierung<select name="smtp_authentication"><option value="credentials" @selected(old('smtp_authentication',data_get($profile->options,'authentication','credentials'))==='credentials')>Mit Postfach und Passwort</option><option value="none" @selected(old('smtp_authentication',data_get($profile->options,'authentication'))==='none')>Ohne Anmeldung</option></select><span class="muted">LOGIN oder PLAIN wird automatisch ausgehandelt.</span></label>
<label class="field">Absenderadresse<input type="email" name="smtp_from_address" value="{{ old('smtp_from_address',data_get($profile->options,'from_address')) }}" placeholder="rechnung@firma.de"></label>
<label class="field">Absendername<input name="smtp_from_name" value="{{ old('smtp_from_name',data_get($profile->options,'from_name','DB-Webapp')) }}" placeholder="Rechnungswesen"></label>
</div></section>
<section class="form-section" data-types="filesystem"><div class="form-section-head"><div><h2>Dateisystem</h2><p class="section-note">Mount, Zugriffsmodus und Ablageschema.</p></div><x-help-tip title="Dateisystem">„Nur lesen“ verhindert neue Dateien. „Lesen und schreiben“ ist nur für freigegebene Ziele wie storage.rechnungen gedacht. UNC-Zielpfad wird in der Datenbank gespeichert; das Dateinamenschema steuert Ordner und Dateinamen neuer Rechnungen.</x-help-tip></div><div class="section-grid">
<label class="field">Modus<select name="filesystem_mode"><option value="read-only" @selected(old('filesystem_mode',data_get($profile->options,'mode','read-only'))==='read-only')>Nur lesen</option><option value="read-write" @selected(old('filesystem_mode',data_get($profile->options,'mode'))==='read-write')>Lesen und schreiben</option></select></label>
<label class="field">UNC-Zielpfad<input name="unc_root" value="{{ old('unc_root',data_get($profile->options,'unc_root')) }}" placeholder="\\server\freigabe"></label>
<label class="field wide">Dateinamenschema<input name="filename_pattern" value="{{ old('filename_pattern',data_get($profile->options,'filename_pattern')) }}" placeholder="{year}/Rechnungen/Papier/{invoice_number}.pdf"><span class="muted">Platzhalter: {year} und {invoice_number}</span></label>
</div></section>
<section class="form-section"><div class="form-section-head"><div><h2>Erweiterte Optionen</h2><p class="section-note">Nur für zusätzliche technische Werte, die oben nicht angeboten werden.</p></div><x-help-tip title="Erweiterte Optionen">Nur für technische Zusatzwerte verwenden, die kein eigenes Feld besitzen. Das JSON muss gültig sein. Bekannte Standardoptionen besser über die vorgesehenen Felder pflegen, damit keine widersprüchlichen Einstellungen entstehen.</x-help-tip></div>
<label class="field">Optionen als JSON<textarea name="options_text" placeholder='{"verify_tls":true}'>{{ old('options_text',$profile->options ? json_encode($profile->options,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : '') }}</textarea></label>
</section>
</div><p><button class="btn">Speichern</button></p></form></section></main>
<script>
const typeSelect=document.getElementById('profile-type');
function updateTypeSections(){const type=typeSelect.value;document.querySelectorAll('[data-types]').forEach(element=>{const visible=element.dataset.types.split(' ').includes(type);element.classList.toggle('hidden',!visible);element.querySelectorAll('input,select,textarea').forEach(field=>field.disabled=!visible)})}
typeSelect.addEventListener('change',updateTypeSections);updateTypeSections();
</script>
<script src="{{ asset('js/db-window-manager.js') }}?v=20260918-2"></script>
</body></html>
