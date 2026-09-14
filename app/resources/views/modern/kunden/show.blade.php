<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $kunde->strName }} · tops.net</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f4f6f8;
            color: #1f2937;
        }

        .page {
            max-width: 1350px;
            margin: auto;
            padding: 32px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 26px;
        }

        .links {
            display: flex;
            gap: 18px;
        }

        a {
            color: #0369a1;
            text-decoration: none;
        }

        .title-card,
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
        }

        .title-card {
            padding: 24px;
            margin-bottom: 20px;
        }

        .title-card h1 {
            margin: 0 0 5px;
        }

        .muted {
            color: #64748b;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .card {
            padding: 22px;
        }

        .card h2 {
            margin-top: 0;
        }

        .field {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .label {
            color: #64748b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        th {
            color: #64748b;
        }

        .full {
            grid-column: 1 / -1;
        }

        @media (max-width: 850px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .page {
                padding: 18px;
            }

            .field {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="page">

    @if(session('success'))<div style="padding:10px 12px; background:#ecfdf5; border-radius:8px; margin-bottom:12px;">{{ session('success') }}</div>@endif
    @if(session('warning'))<div style="padding:10px 12px; background:#fffbeb; border:1px solid #f59e0b; border-radius:8px; margin-bottom:12px;">{{ session('warning') }}</div>@endif

<div class="topbar">
    <div style="display:flex; gap:18px;">
        <a href="{{ route('kunden.index') }}">
            ← Kunden
        </a>

        <a href="{{ route('dashboard') }}">
            Hauptmenü
        </a>

        <a href="{{ route('kunden.edit', $kunde->intID) }}">
            Kunde bearbeiten
        </a>

        <a href="{{ route('kunden.create') }}">Neuer Kunde</a>
        <a href="{{ route('kunden.branchen.edit',$kunde->intID) }}">Branchen</a>
        <a href="{{ route('kunden.rechnungsanschriften.index',$kunde->intID) }}">Rechnungsanschriften</a>
        <a href="{{ route('kunden.offene-rechnungen.index',$kunde->intID) }}">Offene Rechnungen</a>
    </div>

    <a href="{{ route('frontend.switch', 'classic') }}">
        ← Klassische Ansicht
    </a>
</div>

    <div class="title-card">

        <h1>{{ $kunde->strName }}</h1>

        <div class="muted">
            Kundennummer {{ $kunde->intID }}
        </div>

    </div>

    <div class="grid">

        <div class="card">

            <h2>Stammdaten</h2>

            <div class="field">
                <div class="label">Anrede</div>
                <div>{{ $kunde->strAnrede }}</div>
            </div>

            <div class="field">
                <div class="label">Straße</div>
                <div>{{ $kunde->strStrasse }}</div>
            </div>

            <div class="field">
                <div class="label">Ort</div>
                <div>{{ $kunde->strPLZ }} {{ $kunde->strOrt }}</div>
            </div>

            <div class="field">
                <div class="label">Telefon</div>
                <div>{{ $kunde->strTelefon }}</div>
            </div>

            <div class="field">
                <div class="label">E-Mail</div>
                <div>{{ $kunde->strEmail }}</div>
            </div>

<div class="field">
    <div class="label">Kunde seit</div>
    <div>
        {{ $kunde->datKundeSeit
            ? \Carbon\Carbon::parse($kunde->datKundeSeit)->format('d.m.Y')
            : '' }}
    </div>
</div>
        </div>

        <div class="card">

            <h2>Status</h2>

            <div class="field">
                <div class="label">Aktiver Kunde</div>
                <div>{{ $kunde->boolAktiverKunde ? 'Ja' : 'Nein' }}</div>
            </div>

            <div class="field">
                <div class="label">Lastschrift</div>
                <div>{{ $kunde->boolLastschrift ? 'Ja' : 'Nein' }}</div>
            </div>

            <div class="field">
                <div class="label">DATEV-Konto</div>
                <div>{{ $kunde->strDatevKundenKonto }}</div>
            </div>

            <div class="field">
                <div class="label">Rahmenvertrag</div>
                <div>{{ $kunde->rahmenvertragda ? 'Ja' : 'Nein' }}</div>
            </div>

            <div class="field">
                <div class="label">WebDNS erlaubt</div>
                <div>{{ $kunde->bWEBDNSistErlaubt ? 'Ja' : 'Nein' }}</div>
            </div>
	    <div class="field">
	        <div class="label">Zahlungsbedingung</div>
     		<div>
        	   {{ $kunde->zahlungsbedingung?->strBezeichnung ?? 'Nicht hinterlegt' }}
    		</div>
            </div>

        <div class="card full">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h2>Branchen</h2>
                <a href="{{ route('kunden.branchen.edit',$kunde->intID) }}">Auswahl ändern</a>
            </div>
            @forelse($branchen as $branche)
                <span style="display:inline-block; padding:6px 9px; border-radius:999px; background:#eef2f7; margin:3px;">{{ $branche->strCode }} · {{ $branche->strBezeichnung }}</span>
            @empty
                <span class="muted">Keine Branchen zugeordnet.</span>
            @endforelse
        </div>

        <div class="card full">

            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h2>Ansprechpartner</h2>
                <a href="{{ route('kunden.ansprechpartner.create',$kunde->intID) }}">+ Neu</a>
            </div>

            @if ($kunde->ansprechpartner->isEmpty())

                <p class="muted">
                    Keine Ansprechpartner vorhanden.
                </p>

            @else

                <table>
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Funktion</th>
                        <th>Telefon</th>
                        <th>Mobil</th>
                        <th>E-Mail</th>
                        <th></th>
                    </tr>
                    </thead>

                    <tbody>

                    @foreach ($kunde->ansprechpartner as $ansprechpartner)

                        <tr>

                            <td>
                                {{ $ansprechpartner->strVorname }}
                                {{ $ansprechpartner->strName }}
                            </td>

                            <td>{{ $ansprechpartner->strFunktion }}</td>
                            <td>{{ $ansprechpartner->strtel1 }}</td>
                            <td>{{ $ansprechpartner->strtelmobil1 }}</td>
                            <td>{{ $ansprechpartner->stremail1 }}</td>
                            <td><a href="{{ route('kunden.ansprechpartner.edit',[$kunde->intID,$ansprechpartner->intID]) }}">Bearbeiten</a></td>

                        </tr>

                    @endforeach

                    </tbody>

                </table>

            @endif

        </div>

        <div class="card full">

            <h2>Projekte</h2>

            @if ($kunde->projekte->isEmpty())

                <p class="muted">
                    Keine Projekte vorhanden.
                </p>

            @else

                <table>

                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Beschreibung</th>
                        <th>Start</th>
                    </tr>
                    </thead>

                    <tbody>

                    @foreach ($kunde->projekte as $projekt)

                        <tr>
                            <td>{{ $projekt->intID }}</td>
                            <td>{{ $projekt->strName }}</td>
                            <td>{{ $projekt->strCode }}</td>
                            <td>{{ $projekt->strBeschreibung }}</td>
                            <td>{{ $projekt->datStart }}</td>
                        </tr>

                    @endforeach

                    </tbody>

                </table>

            @endif

        </div>

    </div>

</div>

</body>
</html>
