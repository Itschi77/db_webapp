<?php

namespace App\Http\Controllers;

use App\Jobs\RunSqlServerBackup;
use App\Models\AdminConnectionProfile;
use App\Services\InvoiceEndToEndTestService;
use App\Services\SqlServerBackupService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PDO;
use Throwable;

class AdminController extends Controller
{
    public function index(Request $request, SqlServerBackupService $backupService, InvoiceEndToEndTestService $invoiceEndTestService)
    {
        $request->validate([
            'invoice_endtest' => ['nullable', 'in:1'],
            'endtest_von' => ['nullable', 'date'],
            'endtest_bis' => ['nullable', 'date', 'after_or_equal:endtest_von'],
            'endtest_rechnungsdatum' => ['nullable', 'date'],
        ]);
        $previousMonth = CarbonImmutable::today()->subMonthNoOverflow();
        $endtestVon = CarbonImmutable::parse($request->input('endtest_von', $previousMonth->startOfMonth()->toDateString()))->startOfDay();
        $endtestBis = CarbonImmutable::parse($request->input('endtest_bis', $previousMonth->endOfMonth()->toDateString()))->endOfDay();
        $endtestRechnungsdatum = CarbonImmutable::parse($request->input('endtest_rechnungsdatum', $endtestBis->toDateString()))->startOfDay();
        $invoiceEndTest = $request->input('invoice_endtest') === '1'
            ? $invoiceEndTestService->run($endtestVon, $endtestBis, $endtestRechnungsdatum)
            : null;

        return view('admin.index', [
            'profiles' => AdminConnectionProfile::orderBy('type')->orderBy('name')->get(),
            'logFiles' => collect(glob(storage_path('logs/*.log')) ?: [])
                ->map(fn ($path) => ['name' => basename($path), 'size' => filesize($path), 'modified' => filemtime($path)])
                ->sortByDesc('modified')->values(),
            'backupStatus' => $backupService->status(),
            'recentBackups' => $backupService->recentBackups(),
            'queuedBackups' => DB::table('jobs')->where('queue', 'backups')->count(),
            'invoiceEndTest' => $invoiceEndTest,
            'endtestVon' => $endtestVon->toDateString(),
            'endtestBis' => $endtestBis->toDateString(),
            'endtestRechnungsdatum' => $endtestRechnungsdatum->toDateString(),
        ]);
    }

    public function backup(Request $request, SqlServerBackupService $backupService)
    {
        $data = $request->validate([
            'database' => ['required', 'in:accountings,domains,topsnetdb_safe,all'],
        ]);
        $actor = (string) ($request->attributes->get('ad_username') ?: 'admin');
        $databases = $data['database'] === 'all'
            ? ['accountings', 'domains', 'topsnetdb_safe']
            : [$data['database']];

        $status = $backupService->status();
        foreach ($databases as $database) {
            $dbStatus = $status['databases'][$database] ?? null;
            if (! $dbStatus || ! ($dbStatus['ready'] ?? false)) {
                return redirect()->route('admin.index')->with(
                    'error',
                    'Backup nicht gestartet: Für '.$database.' sind SQL-Verbindung, Backuprecht oder CARDEA-Zielpfad noch nicht bereit.'
                );
            }
        }
        foreach ($databases as $database) {
            RunSqlServerBackup::dispatch($database, $actor);
        }

        Log::notice('Manual SQL Server backup queued', [
            'databases' => $databases,
            'actor' => $actor,
        ]);

        return redirect()->route('admin.index')->with(
            'status',
            count($databases).' Datenbank-Backup(s) wurden zur Hintergrundverarbeitung eingeplant.'
        );
    }

    public function create()
    {
        return view('admin.form', ['profile' => new AdminConnectionProfile()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['updated_by'] = $request->attributes->get('ad_username');
        AdminConnectionProfile::create($data);

        return redirect()->route('admin.index')->with('status', 'Verbindung wurde angelegt.');
    }

    public function edit(AdminConnectionProfile $profile)
    {
        return view('admin.form', compact('profile'));
    }

    public function update(Request $request, AdminConnectionProfile $profile)
    {
        $data = $this->validated($request, $profile);
        if (($data['secret'] ?? '') === '') {
            unset($data['secret']);
        }
        $data['updated_by'] = $request->attributes->get('ad_username');
        $data['last_test_at'] = null;
        $data['last_test_status'] = null;
        $data['last_test_message'] = 'Nach der Änderung ist ein neuer Verbindungstest erforderlich.';
        $profile->update($data);

        return redirect()->route('admin.index')->with('status', 'Verbindung wurde aktualisiert.');
    }
    public function test(Request $request, AdminConnectionProfile $profile)
    {
        [$ok, $message] = $this->testProfile($profile);
        $profile->update([
            'last_test_at' => now(),
            'last_test_status' => $ok ? 'ok' : 'error',
            'last_test_message' => $message,
            'updated_by' => $request->attributes->get('ad_username'),
        ]);

        return redirect()->route('admin.index')->with($ok ? 'status' : 'error', $message);
    }

    public function testMail(Request $request, AdminConnectionProfile $profile)
    {
        abort_unless($profile->type === 'smtp', 404);
        $data = $request->validate(['recipient' => ['required', 'email:rfc', 'max:255']]);

        try {
            $this->configureMailer($profile);
            Mail::mailer('smtp')->raw(
                'Dies ist eine Testnachricht der DB-Webapp. Der SMTP-Versand wurde erfolgreich geprüft.',
                function ($message) use ($profile, $data) {
                    $message->to($data['recipient'])
                        ->from(
                            (string) ($profile->options['from_address'] ?? $profile->username),
                            (string) ($profile->options['from_name'] ?? 'DB-Webapp')
                        )
                        ->subject('DB-Webapp: SMTP-Test erfolgreich');
                }
            );
            $message = 'SMTP-Anmeldung und Testversand an '.$data['recipient'].' waren erfolgreich.';
            $profile->update([
                'last_test_at' => now(), 'last_test_status' => 'ok', 'last_test_message' => $message,
                'updated_by' => $request->attributes->get('ad_username'),
            ]);
            Log::notice('Admin SMTP test mail sent', [
                'profile' => $profile->key, 'recipient' => $data['recipient'],
                'actor' => $request->attributes->get('ad_username'),
            ]);
            return redirect()->route('admin.index')->with('status', $message);
        } catch (Throwable $e) {
            $message = 'SMTP-Testversand fehlgeschlagen: '.$e->getMessage();
            $profile->update([
                'last_test_at' => now(), 'last_test_status' => 'error', 'last_test_message' => $message,
                'updated_by' => $request->attributes->get('ad_username'),
            ]);
            Log::error('Admin SMTP test mail failed', [
                'profile' => $profile->key, 'recipient' => $data['recipient'],
                'actor' => $request->attributes->get('ad_username'), 'error' => $e->getMessage(),
            ]);
            return redirect()->route('admin.index')->with('error', $message);
        }
    }

    public function log(string $filename)
    {
        abort_unless(preg_match('/^[A-Za-z0-9._-]+\.log$/', $filename), 404);
        $path = storage_path('logs/'.$filename);
        abort_unless(is_file($path), 404);

        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        return view('admin.log', [
            'filename' => $filename,
            'content' => implode("\n", array_slice($lines, -500)),
        ]);
    }

    private function validated(Request $request, ?AdminConnectionProfile $profile = null): array
    {
        $id = $profile?->id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9._-]+$/', 'unique:admin_connection_profiles,key,'.$id],
            'type' => ['required', 'in:sqlserver,postgres,smtp,filesystem,http'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'database' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'secret' => [$profile?->exists ? 'nullable' : 'nullable', 'string', 'max:4000'],
            'options_text' => ['nullable', 'json', 'max:10000'],
            'filesystem_mode' => ['nullable', 'in:read-only,read-write'],
            'unc_root' => ['nullable', 'string', 'max:255'],
            'filename_pattern' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', 'in:none,starttls,smtps'],
            'smtp_authentication' => ['nullable', 'in:credentials,none'],
            'smtp_from_address' => ['nullable', 'email:rfc', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ]);
        $optionsText = trim((string) ($data['options_text'] ?? ''));
        $options = $optionsText === '' ? [] : json_decode($optionsText, true, flags: JSON_THROW_ON_ERROR);
        if ($data['type'] === 'filesystem') {
            $options['mode'] = $data['filesystem_mode'] ?? ($options['mode'] ?? 'read-only');
            if (trim((string) ($data['unc_root'] ?? '')) !== '') {
                $options['unc_root'] = trim($data['unc_root']);
            }
            if (trim((string) ($data['filename_pattern'] ?? '')) !== '') {
                $options['filename_pattern'] = trim($data['filename_pattern']);
            }
        }
        if ($data['type'] === 'smtp') {
            $options['encryption'] = $data['smtp_encryption'] ?? ($options['encryption'] ?? 'starttls');
            $options['authentication'] = $data['smtp_authentication'] ?? ($options['authentication'] ?? 'credentials');
            unset($options['auth_mode']);
            $options['from_address'] = trim((string) ($data['smtp_from_address'] ?? ($options['from_address'] ?? '')));
            $options['from_name'] = trim((string) ($data['smtp_from_name'] ?? ($options['from_name'] ?? 'DB-Webapp')));
        }
        unset(
            $data['options_text'], $data['filesystem_mode'], $data['unc_root'], $data['filename_pattern'],
            $data['smtp_encryption'], $data['smtp_authentication'], $data['smtp_from_address'], $data['smtp_from_name']
        );
        $data['options'] = $options ?: null;
        $data['active'] = $request->boolean('active');

        return $data;
    }
    private function testProfile(AdminConnectionProfile $profile): array
    {
        try {
            return match ($profile->type) {
                'sqlserver' => $this->testPdo(
                    'sqlsrv:Server='.$profile->host.($profile->port ? ','.$profile->port : '').';Database='.$profile->database.';Encrypt=yes;TrustServerCertificate=yes',
                    $profile
                ),
                'postgres' => $this->testPdo(
                    'pgsql:host='.$profile->host.';port='.($profile->port ?: 5432).';dbname='.$profile->database,
                    $profile
                ),
                'smtp' => $this->testSocket($profile),
                'filesystem' => $this->testFilesystem($profile),
                'http' => $this->testHttp($profile),
                default => [false, 'Unbekannter Verbindungstyp.'],
            };
        } catch (Throwable $e) {
            return [false, 'Verbindung fehlgeschlagen: '.$e->getMessage()];
        }
    }

    private function testPdo(string $dsn, AdminConnectionProfile $profile): array
    {
        $pdo = new PDO($dsn, $profile->username, $profile->secret, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->query('SELECT 1');
        return [true, 'Datenbankverbindung erfolgreich getestet.'];
    }

    private function testSocket(AdminConnectionProfile $profile): array
    {
        $encryption = $profile->options['encryption'] ?? (($profile->options['tls'] ?? false) ? 'smtps' : 'none');
        $target = $encryption === 'smtps' ? 'ssl://'.$profile->host : $profile->host;
        $socket = @fsockopen($target, $profile->port ?: 25, $errno, $error, 5);
        if (! $socket) {
            return [false, "SMTP-Verbindung fehlgeschlagen: {$error} ({$errno})."];
        }
        fclose($socket);
        return [true, 'SMTP-Server ist erreichbar. Anmeldung und Versand wurden nicht ausgeführt.'];
    }

    private function configureMailer(AdminConnectionProfile $profile): void
    {
        $encryption = $profile->options['encryption'] ?? 'starttls';
        $authentication = $profile->options['authentication'] ?? 'credentials';
        $fromAddress = trim((string) ($profile->options['from_address'] ?? $profile->username));
        if (! filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Für das SMTP-Profil fehlt eine gültige Absenderadresse.');
        }
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.scheme' => $encryption === 'smtps' ? 'smtps' : null,
            'mail.mailers.smtp.auto_tls' => $encryption !== 'none',
            'mail.mailers.smtp.host' => $profile->host,
            'mail.mailers.smtp.port' => $profile->port ?: ($encryption === 'smtps' ? 465 : 587),
            'mail.mailers.smtp.username' => $authentication === 'none' ? null : $profile->username,
            'mail.mailers.smtp.password' => $authentication === 'none' ? null : $profile->secret,
            'mail.from.address' => $fromAddress,
            'mail.from.name' => (string) ($profile->options['from_name'] ?? 'DB-Webapp'),
        ]);
        app('mail.manager')->purge('smtp');
    }

    private function testFilesystem(AdminConnectionProfile $profile): array
    {
        $path = $profile->host;
        if (! $path || ! str_starts_with($path, '/') || ! is_dir($path)) {
            return [false, 'Das konfigurierte absolute Verzeichnis ist nicht vorhanden.'];
        }
        if (! is_readable($path)) {
            return [false, "Verzeichnis ist nicht lesbar: {$path}"];
        }

        $requiredMode = $profile->options['mode'] ?? 'read-only';
        if ($requiredMode !== 'read-write') {
            return [true, "Read-only-Verzeichnis erfolgreich geprüft: {$path}"];
        }
        if (! is_writable($path)) {
            return [false, "Rechnungsablage ist nicht schreibbar: {$path}"];
        }

        $testFile = rtrim($path, '/').'/.db-webapp-write-test-'.Str::uuid();
        try {
            if (file_put_contents($testFile, 'write-test') === false || file_get_contents($testFile) !== 'write-test') {
                return [false, 'Testdatei konnte nicht verifiziert werden.'];
            }
        } finally {
            if (is_file($testFile)) {
                unlink($testFile);
            }
        }
        return [true, "Schreibtest erfolgreich; Testdatei wurde entfernt: {$path}"];
    }

    private function testHttp(AdminConnectionProfile $profile): array
    {
        $url = $profile->host;
        $response = Http::timeout(5)->withOptions(['verify' => $profile->options['verify_tls'] ?? true])->get($url);
        return [$response->successful(), 'HTTP-Test lieferte Status '.$response->status().'.'];
    }
}
