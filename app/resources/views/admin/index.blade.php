<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Administration</title>
<style>
*{box-sizing:border-box}body{font-family:system-ui,Segoe UI,Arial;background:#f3f6fa;color:#172033;margin:0}
.page{max-width:1450px;margin:auto;padding:28px}.top,.section-head,.profile-head,.actions{display:flex;align-items:center;gap:12px}
.top,.section-head,.profile-head{justify-content:space-between}.top h1,.section-head h2,.profile-head h3{margin:0}
.subtitle,.muted{color:#667085}.subtitle{margin-top:4px}.muted{font-size:12px}
.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:18px}.summary-card,.section{background:#fff;border:1px solid #dfe5ee;border-radius:13px;box-shadow:0 3px 14px #1f29370c}
.summary-card{padding:15px}.summary-card strong{display:block;font-size:24px}.section{margin-top:16px;padding:18px}
.profiles{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px;margin-top:14px}
.profile{border:1px solid #e1e7ef;border-radius:11px;padding:15px;background:#f9fbfd;min-width:0}
.profile-title{display:flex;align-items:center;gap:8px}.status-dot{width:10px;height:10px;border-radius:50%;background:#98a2b3;flex:0 0 auto}
.status-dot.ok{background:#16a34a}.status-dot.error{background:#dc2626}.profile-key{font-family:ui-monospace,monospace;font-size:12px;color:#667085;margin-top:3px}
.meta{display:grid;grid-template-columns:100px 1fr;gap:6px 10px;margin:14px 0;font-size:13px}.meta span:nth-child(odd){color:#667085}.target{overflow-wrap:anywhere}
.test-result{min-height:35px;padding:9px;border-radius:7px;background:#eef2f6;font-size:12px;margin-bottom:12px}.test-result.ok{background:#e8f7ee;color:#176b3a}.test-result.error{background:#fff0ee;color:#b42318}
.btn{display:inline-block;border:0;border-radius:7px;background:#1f4f8a;color:#fff;padding:8px 11px;text-decoration:none;font-weight:700;cursor:pointer;font-size:12px}.btn.gray{background:#526071}
.actions{justify-content:flex-start;flex-wrap:wrap}.testmail{display:flex;gap:6px;flex-wrap:wrap}.testmail input{padding:7px;border:1px solid #ccd3df;border-radius:7px;width:180px}
.empty{padding:18px;color:#667085;border:1px dashed #ccd3df;border-radius:9px}.flash{padding:11px 13px;border-radius:8px;margin-top:12px;background:#e8f7ee}.flash.error{background:#fff0ee;color:#b42318}
details{margin-top:12px}summary{cursor:pointer;font-weight:700}.logs{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:12px}.log{display:flex;justify-content:space-between;align-items:center;border:1px solid #e1e7ef;border-radius:8px;padding:10px}
@media(max-width:900px){.summary{grid-template-columns:repeat(2,1fr)}.profiles,.logs{grid-template-columns:1fr}}@media(max-width:560px){.summary{grid-template-columns:1fr}.page{padding:16px}.top{align-items:flex-start}}
</style></head>
<body><main class="page">
<div class="top"><div><h1>Administration</h1><div class="subtitle">Verbindungen, Systemstatus und Protokolle</div></div><a href="{{ route('dashboard') }}">← Hauptmenü</a></div>
@if(session('status'))<div class="flash">{{ session('status') }}</div>@endif
@if(session('error'))<div class="flash error">{{ session('error') }}</div>@endif
@php
$dbProfiles=$profiles->whereIn('type',['sqlserver','postgres']);
$mailProfiles=$profiles->where('type','smtp');
$filesystemProfiles=$profiles->where('type','filesystem');
$httpProfiles=$profiles->where('type','http');
$otherProfiles=$profiles->whereNotIn('type',['sqlserver','postgres','smtp','filesystem','http']);
$okCount=$profiles->where('last_test_status','ok')->count();
$errorCount=$profiles->where('last_test_status','error')->count();
@endphp
<div class="summary">
<div class="summary-card"><span class="muted">Verbindungen</span><strong>{{ $profiles->count() }}</strong></div>
<div class="summary-card"><span class="muted">Aktiv</span><strong>{{ $profiles->where('active',true)->count() }}</strong></div>
<div class="summary-card"><span class="muted">Test erfolgreich</span><strong style="color:#176b3a">{{ $okCount }}</strong></div>
<div class="summary-card"><span class="muted">Fehler / offen</span><strong style="color:{{ $errorCount ? '#b42318' : '#667085' }}">{{ $profiles->count()-$okCount }}</strong></div>
</div>
@php
$groups=[
 ['title'=>'Datenbanken','note'=>'SQL Server und PostgreSQL','items'=>$dbProfiles],
 ['title'=>'E-Mail-Versand','note'=>'SMTP-Postfach für Test- und Rechnungs-E-Mails','items'=>$mailProfiles],
 ['title'=>'Dateien & Netzwerkpfade','note'=>'Ablagepfade und Dateisystem-Verbindungen','items'=>$filesystemProfiles],
 ['title'=>'Dienste & System','note'=>'HTTP-/HTTPS-Dienste und technische Endpunkte','items'=>$httpProfiles],
];
if ($otherProfiles->isNotEmpty()) {
 $groups[]=['title'=>'Weitere Verbindungen','note'=>'Noch keinem Standardbereich zugeordnete Verbindungen','items'=>$otherProfiles];
}
@endphp
@foreach($groups as $group)
<section class="section">
<div class="section-head"><div><h2>{{ $group['title'] }}</h2><div class="muted">{{ $group['note'] }}</div></div>@if($loop->first)<a class="btn" href="{{ route('admin.connections.create') }}">Neue Verbindung</a>@endif</div>
<div class="profiles">
@forelse($group['items'] as $profile)
<article class="profile">
<div class="profile-head"><div><div class="profile-title"><span class="status-dot {{ $profile->last_test_status }}"></span><h3>{{ $profile->name }}</h3></div><div class="profile-key">{{ $profile->key }}</div></div><strong>{{ strtoupper($profile->type) }}</strong></div>
<div class="meta">
<span>Ziel</span><span class="target">{{ $profile->host ?: 'noch nicht eingetragen' }}@if($profile->port):{{ $profile->port }}@endif</span>
@if($profile->database)<span>Datenbank</span><span>{{ $profile->database }}</span>@endif
<span>Aktiv</span><span>{{ $profile->active ? 'Ja' : 'Nein' }}</span>
<span>Geändert von</span><span>{{ $profile->updated_by ?: '–' }}</span>
</div>
<div class="test-result {{ $profile->last_test_status }}"><strong>{{ $profile->last_test_at?->format('d.m.Y H:i') ?: 'Noch nicht getestet' }}</strong><br>{{ $profile->last_test_message ?: 'Kein Testergebnis vorhanden.' }}</div>
<div class="actions">
<a class="btn gray" href="{{ route('admin.connections.edit',$profile) }}">Bearbeiten</a>
<form method="post" action="{{ route('admin.connections.test',$profile) }}">@csrf<button class="btn">Erreichbarkeit testen</button></form>
@if($profile->type==='smtp')
<form class="testmail" method="post" action="{{ route('admin.connections.test-mail',$profile) }}" onsubmit="return confirm('Testmail wirklich an '+this.recipient.value+' senden?')">
@csrf<input type="email" name="recipient" value="sw@tops.net" required><button class="btn">Testmail senden</button>
</form>
@endif
</div>
</article>
@empty
<div class="empty">In diesem Bereich ist noch keine Verbindung angelegt.</div>
@endforelse
</div>
</section>
@endforeach
<section class="section">
<div class="section-head"><div><h2>Anwendungslogs</h2><div class="muted">Nur bei Bedarf öffnen; angezeigt werden jeweils die letzten 500 Zeilen.</div></div></div>
<details><summary>{{ $logFiles->count() }} Logdatei(en) anzeigen</summary>
<div class="logs">
@forelse($logFiles as $log)
<div class="log"><div><strong>{{ $log['name'] }}</strong><div class="muted">{{ number_format($log['size']/1024,1,',','.') }} KB · {{ date('d.m.Y H:i',$log['modified']) }}</div></div><a class="btn gray" href="{{ route('admin.logs.show',$log['name']) }}">Öffnen</a></div>
@empty<div class="empty">Keine Logs vorhanden.</div>@endforelse
</div></details>
</section>
</main>
<script src="{{ asset('js/db-window-manager.js') }}?v=20260918-3"></script>
</body></html>
