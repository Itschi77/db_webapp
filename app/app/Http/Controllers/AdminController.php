<?php

namespace App\Http\Controllers;

use App\Models\AdminConnectionProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use PDO;
use Throwable;

class AdminController extends Controller
{
    public function index()
    {
        return view('admin.index', [
            'profiles' => AdminConnectionProfile::orderBy('type')->orderBy('name')->get(),
            'logFiles' => collect(glob(storage_path('logs/*.log')) ?: [])
                ->map(fn ($path) => ['name' => basename($path), 'size' => filesize($path), 'modified' => filemtime($path)])
                ->sortByDesc('modified')->values(),
        ]);
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
            'active' => ['nullable', 'boolean'],
        ]);
        $optionsText = trim((string) ($data['options_text'] ?? ''));
        unset($data['options_text']);
        $data['options'] = $optionsText === '' ? null : json_decode($optionsText, true, flags: JSON_THROW_ON_ERROR);
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
        $target = (($profile->options['tls'] ?? false) ? 'tls://' : '').$profile->host;
        $socket = @fsockopen($target, $profile->port ?: 25, $errno, $error, 5);
        if (! $socket) {
            return [false, "SMTP-Verbindung fehlgeschlagen: {$error} ({$errno})."];
        }
        fclose($socket);
        return [true, 'SMTP-Server ist erreichbar. Anmeldung und Versand wurden nicht ausgeführt.'];
    }
    private function testFilesystem(AdminConnectionProfile $profile): array
    {
        $path = $profile->host;
        if (! $path || ! is_dir($path)) {
            return [false, 'Das konfigurierte Verzeichnis ist nicht vorhanden.'];
        }
        $mode = is_writable($path) ? 'les- und schreibbar' : (is_readable($path) ? 'nur lesbar' : 'nicht lesbar');
        return [is_readable($path), "Verzeichnis ist {$mode}: {$path}"];
    }

    private function testHttp(AdminConnectionProfile $profile): array
    {
        $url = $profile->host;
        $response = Http::timeout(5)->withOptions(['verify' => $profile->options['verify_tls'] ?? true])->get($url);
        return [$response->successful(), 'HTTP-Test lieferte Status '.$response->status().'.'];
    }
}
