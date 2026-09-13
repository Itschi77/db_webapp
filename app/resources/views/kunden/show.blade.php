<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $kunde->strName }}</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background: #f5f5f5;
        }

        .container {
            max-width: 1200px;
            margin: auto;
            background: white;
            padding: 24px;
            border-radius: 8px;
        }

        .section {
            margin-top: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #eee;
        }

        .label {
            font-weight: bold;
            display: inline-block;
            width: 180px;
        }

        a {
            color: #005ea8;
            text-decoration: none;
        }
    </style>
</head>

<body>

<div class="container">

    <p>
        <a href="{{ route('kunden.index') }}">
            ← Kundenübersicht
        </a>
    </p>

    <h1>{{ $kunde->strName }}</h1>

    <div>
        <p>
            <span class="label">Kundennummer:</span>
            {{ $kunde->intID }}
        </p>

        <p>
            <span class="label">Adresse:</span>
            {{ $kunde->strStrasse }},
            {{ $kunde->strPLZ }}
            {{ $kunde->strOrt }}
        </p>

        <p>
            <span class="label">Telefon:</span>
            {{ $kunde->strTelefon }}
        </p>

        <p>
            <span class="label">E-Mail:</span>
            {{ $kunde->strEmail }}
        </p>

        <p>
            <span class="label">Kunde seit:</span>
            {{ $kunde->datKundeSeit }}
        </p>

        <p>
            <span class="label">Aktiver Kunde:</span>
            {{ $kunde->boolAktiverKunde ? 'Ja' : 'Nein' }}
        </p>

        <p>
            <span class="label">Info:</span>
            {{ $kunde->txtInfo }}
        </p>
    </div>

    <div class="section">

        <h2>Ansprechpartner</h2>

        @if ($kunde->ansprechpartner->isEmpty())

            <p>Keine Ansprechpartner vorhanden.</p>

        @else

            <table>
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Funktion</th>
                    <th>Telefon</th>
                    <th>Mobil</th>
                    <th>E-Mail</th>
                </tr>
                </thead>

                <tbody>

                @foreach ($kunde->ansprechpartner as $ansprechpartner)

                    <tr>
                        <td>
                            {{ $ansprechpartner->strVorname }}
                            {{ $ansprechpartner->strName }}
                        </td>

                        <td>
                            {{ $ansprechpartner->strFunktion }}
                        </td>

                        <td>
                            {{ $ansprechpartner->strtel1 }}
                        </td>

                        <td>
                            {{ $ansprechpartner->strtelmobil1 }}
                        </td>

                        <td>
                            {{ $ansprechpartner->stremail1 }}
                        </td>
                    </tr>

                @endforeach

                </tbody>
            </table>

        @endif

    </div>

    <div class="section">

        <h2>Projekte</h2>

        @if ($kunde->projekte->isEmpty())

            <p>Keine Projekte vorhanden.</p>

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

</body>
</html>
