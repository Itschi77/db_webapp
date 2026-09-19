<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Kundenverwaltung</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 18px;
            font-family: Arial, sans-serif;
            font-size: 14px;
            background: #d9d9d9;
            color: #111;
        }

        .window {
            max-width: 1400px;
            margin: auto;
            background: #efefef;
            border: 1px solid #8c8c8c;
            padding: 16px;
        }

        h1 {
            margin-top: 0;
            font-size: 20px;
        }

        .toolbar {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 16px;
        }

        input,
        select,
        button {
            font: inherit;
            padding: 7px 9px;
        }

        input {
            width: 370px;
        }

        button,
        .button {
            border: 1px solid #777;
            background: #eee;
            color: #111;
            text-decoration: none;
            padding: 7px 14px;
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th,
        td {
            border: 1px solid #b5b5b5;
            padding: 7px;
            text-align: left;
        }

        th {
            background: #dedede;
        }

        a {
            color: #000080;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 14px;
            padding-top: 10px;
            border-top: 1px solid #999;
        }
    </style>
</head>

<body>
<x-page-help title="Kundenverwaltung">Hier suchst und pflegst du Kundenstammdaten. Suchen und Anzeigen ist lesend. Neu anlegen, Bearbeiten sowie Änderungen an Ansprechpartnern oder Branchen schreiben Kundendaten. Vor dem Speichern Kundennummer, Anschrift und Zuordnungen prüfen.</x-page-help>


<div class="window">

    <h1>Kundenverwaltung</h1>

    <form method="GET" class="toolbar">

        <input
            type="text"
            name="q"
            value="{{ request('q') }}"
            placeholder="Kundennummer, Name, Ort, PLZ, E-Mail, Telefon..."
        >

        <select name="status">
            <option value="">Alle Kunden</option>

            <option
                value="aktiv"
                @selected(request('status') === 'aktiv')
            >
                Nur aktive
            </option>

            <option
                value="inaktiv"
                @selected(request('status') === 'inaktiv')
            >
                Nur inaktive
            </option>
        </select>

        <button type="submit">
            Suchen
        </button>

        <a class="button" href="{{ route('kunden.index') }}">
            Filter löschen
        </a>

        <a class="button" href="{{ route('kunden.create') }}">
            Neuer Kunde
        </a>

        <a class="button" href="{{ route('dashboard') }}">
            Hauptmenü
        </a>

    </form>

    <table>
        <thead>
        <tr>
            <th>Kundennummer</th>
            <th>Name</th>
            <th>PLZ</th>
            <th>Ort</th>
            <th>Telefon</th>
            <th>E-Mail</th>
            <th>Aktiv</th>
        </tr>
        </thead>

        <tbody>

        @forelse ($kunden as $kunde)

            <tr>
                <td>{{ $kunde->intID }}</td>

                <td>
                    <a href="{{ route('kunden.show', $kunde->intID) }}">
                        {{ $kunde->strName }}
                    </a>
                </td>

                <td>{{ $kunde->strPLZ }}</td>
                <td>{{ $kunde->strOrt }}</td>
                <td>{{ $kunde->strTelefon }}</td>
                <td>{{ $kunde->strEmail }}</td>

                <td>
                    {{ $kunde->boolAktiverKunde ? 'Ja' : 'Nein' }}
                </td>
            </tr>

        @empty

            <tr>
                <td colspan="7">
                    Keine Kunden gefunden.
                </td>
            </tr>

        @endforelse

        </tbody>
    </table>

    <div style="margin-top:15px;">
        {{ $kunden->links() }}
    </div>

    <div class="footer">

        <span>
            tops.net Buchhaltung
        </span>

        <a href="{{ route('frontend.switch', 'modern') }}">
            Zum neuen Frontend wechseln →
        </a>

    </div>

</div>

<script src="{{ asset('js/db-window-manager.js') }}"></script></body>
</html>
