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
.backup-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:14px}.backup-card{border:1px solid #e1e7ef;border-radius:11px;padding:14px;background:#f9fbfd}.backup-card h3{margin:0 0 10px}.backup-meta{display:grid;grid-template-columns:120px 1fr;gap:6px 10px;font-size:13px}.backup-meta span:nth-child(odd){color:#667085}.backup-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.btn[disabled]{opacity:.45;cursor:not-allowed}.backup-table{width:100%;border-collapse:collapse;margin-top:12px;font-size:13px}.backup-table th,.backup-table td{text-align:left;padding:8px;border-bottom:1px solid #e7ebf1}.sql-note{margin-top:12px;padding:10px;border-radius:8px;background:#fff8e7;border:1px solid #f1d89b;font-size:12px}.sql-note code{font-family:ui-monospace,monospace}
@media(max-width:900px){.summary{grid-template-columns:repeat(2,1fr)}.profiles,.logs,.backup-grid{grid-template-columns:1fr}}@media(max-width:560px){.summary{grid-template-columns:1fr}.page{padding:16px}.top{align-items:flex-start}}
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
@if($group['title']==='Datenbanken')
<div style="margin-top:18px;padding-top:16px;border-top:1px solid #e1e7ef">
<div class="section-head">
<div><h3 style="margin:0">Backups</h3><div class="muted">Manuelle Vollsicherungen direkt auf <strong>CARDEA</strong>. Keine Kopie nach Janus.</div></div>
<div class="muted">Queue: {{ $queuedBackups }} Auftrag/Aufträge</div>
</div>
<div class="backup-grid">
@foreach($backupStatus['databases'] as $db)
@php
$dbReady=$db['ready'] ?? false;
@endphp
<article class="backup-card">
<h3>{{ $db['database'] }}</h3>
<div class="backup-meta">
<span>Datenbankgröße</span><span>{{ $db['size_mb'] >= 1024 ? number_format($db['size_mb']/1024,1,',','.') .' GB' : number_format($db['size_mb'],1,',','.') .' MB' }}</span>
<span>Letztes Full-Backup</span><span>{{ !empty($db['last_backup_at']) ? date('d.m.Y H:i', strtotime($db['last_backup_at'])) : 'unbekannt' }}</span>
<span>Letzte Backupgröße</span><span>{{ !empty($db['last_backup_bytes']) ? number_format($db['last_backup_bytes']/1073741824,2,',','.') .' GB' : 'unbekannt' }}</span>
<span>Backuprecht</span><span>{{ $db['can_backup'] ? 'Ja' : 'Nein' }}</span>
<span>CARDEA-Ziel</span><span class="target">{{ $db['backup_path'] ?: 'nicht ermittelbar' }}</span>
<span>Status</span><span>{{ $dbReady ? 'Bereit' : ((!$db['connection_ok']) ? 'SQL-Verbindung fehlerhaft' : ((!$db['can_backup']) ? 'Backuprecht fehlt' : 'Zielpfad fehlt')) }}</span>
</div>
<form class="backup-actions" method="post" action="{{ route('admin.backups.create') }}" onsubmit="return confirm('Backup von {{ $db['database'] }} direkt auf CARDEA starten?')">
@csrf
<input type="hidden" name="database" value="{{ $db['database'] }}">
<button class="btn" @disabled(!$dbReady)>{{ $db['database'] }} sichern</button>
</form>
</article>
@endforeach
</div>
<div class="backup-actions">
<form method="post" action="{{ route('admin.backups.create') }}" onsubmit="return confirm('Alle drei Datenbanken nacheinander direkt auf CARDEA sichern? Der accountings-Lauf kann längere Zeit dauern.')">
@csrf<input type="hidden" name="database" value="all">
<button class="btn" @disabled(!$backupStatus['ready'])>Alle drei sichern</button>
</form>
</div>
@if(count($recentBackups))
<details open><summary>Letzte SQL-Server-Sicherungen</summary>
<table class="backup-table">
<thead><tr><th>Fertig</th><th>Datenbank</th><th>CARDEA-Pfad</th><th>Größe</th><th>Typ</th></tr></thead>
<tbody>
@foreach($recentBackups as $backup)
<tr>
<td>{{ $backup['completed_at'] ? date('d.m.Y H:i:s', strtotime($backup['completed_at'])) : '–' }}</td>
<td>{{ $backup['database'] }}</td>
<td><code>{{ $backup['path'] ?: '–' }}</code></td>
<td>{{ number_format(($backup['bytes'] ?? 0)/1073741824,2,',','.') }} GB</td>
<td>{{ $backup['copy_only'] ? 'COPY_ONLY' : 'Vollbackup' }}</td>
</tr>
@endforeach
</tbody></table>
</details>
@else
<div class="empty" style="margin-top:12px">In der SQL-Server-Backup-Historie wurden noch keine Vollsicherungen gefunden.</div>
@endif
</div>
@endif
</section>
@endforeach
<section class="section">
<div class="section-head">
<div><h2>Rechnungstool-Systemtest</h2><div class="muted">Read-only Endtest für Fakturierung, PDF-Renderer und Ablagepfade.</div></div>
@if($invoiceEndTest)<strong style="color:{{ $invoiceEndTest['complete'] ? '#176b3a' : '#b42318' }}">{{ $invoiceEndTest['complete'] ? 'Bestanden' : 'Offene Punkte' }}</strong>@endif
</div>
<form method="get" action="{{ route('admin.index') }}" class="backup-actions" style="align-items:end">
<label class="muted">Von<br><input type="date" name="endtest_von" value="{{ $endtestVon }}" style="padding:7px;border:1px solid #ccd3df;border-radius:7px"></label>
<label class="muted">Bis<br><input type="date" name="endtest_bis" value="{{ $endtestBis }}" style="padding:7px;border:1px solid #ccd3df;border-radius:7px"></label>
<label class="muted">Rechnungsdatum<br><input type="date" name="endtest_rechnungsdatum" value="{{ $endtestRechnungsdatum }}" style="padding:7px;border:1px solid #ccd3df;border-radius:7px"></label>
<button class="btn" type="submit" name="invoice_endtest" value="1">Endtest starten</button>
</form>
@if($invoiceEndTest)
<div class="{{ $invoiceEndTest['complete'] ? 'flash' : 'sql-note' }}" style="margin-top:12px">
<strong>{{ $invoiceEndTest['passedCases'] }}/{{ $invoiceEndTest['cases']->count() }} Falltests bestanden</strong> · {{ $invoiceEndTest['failedCases'] }} fehlgeschlagen · {{ $invoiceEndTest['missingCases'] }} ohne passenden Fall · {{ $invoiceEndTest['failedSystemChecks'] }} Systemfehler · Laufzeit {{ number_format($invoiceEndTest['durationMs']/1000,1,',','.') }} s
</div>
<table class="backup-table">
<thead><tr><th>Fall</th><th>Status</th><th>Auftrag</th><th>Ergebnis</th></tr></thead>
<tbody>
@foreach($invoiceEndTest['cases'] as $case)
<tr>
<td><strong>{{ $case['label'] }}</strong></td>
<td>{{ $case['status']==='passed' ? 'OK' : ($case['status']==='missing' ? 'Kein Fall' : 'Fehler') }}</td>
<td>@if($case['orderNumber'])<a href="{{ route('fakturierung.index',['ansicht'=>'modern','auftrag'=>$case['orderNumber'],'anzeige'=>'alle']) }}">#{{ $case['orderNumber'] }}</a>@else – @endif</td>
<td>{{ $case['message'] }}</td>
</tr>
@endforeach
</tbody></table>
<div class="backup-grid">
@foreach($invoiceEndTest['systemChecks'] as $check)
<article class="backup-card"><h3>{{ $check['label'] }}</h3><div class="test-result {{ $check['status']==='passed' ? 'ok' : 'error' }}"><strong>{{ $check['status']==='passed' ? 'OK' : 'Fehler' }}</strong><br>{{ $check['message'] }}</div></article>
@endforeach
</div>
@endif
</section>
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
