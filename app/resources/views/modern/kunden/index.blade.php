<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Kunden · tops.net</title>

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
            max-width: 1450px;
            margin: auto;
            padding: 32px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }

        .topbar h1 {
            margin: 0;
            font-size: 30px;
        }

        a {
            color: #0369a1;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .actions {
            display: flex;
            gap: 16px;
        }

        .search-card,
        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
        }

        .search-card {
            padding: 20px;
            margin-bottom: 20px;
        }

        form {
            display: grid;
            grid-template-columns: 1fr 180px auto auto;
            gap: 10px;
        }

        input,
        select,
        button {
            font: inherit;
            padding: 11px 12px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
        }

        button {
            cursor: pointer;
            background: #0f172a;
            color: white;
            border-color: #0f172a;
        }

        .secondary {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            color: #374151;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            padding: 10px 14px;
            text-decoration: none;
        }

        .table-card {
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        th,
        td {
            padding: 14px 16px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .badge {
            display: inline-block;
            border-radius: 999px;
            padding: 4px 9px;
            font-size: 12px;
            background: #e5e7eb;
        }

        .pagination {
            margin-top: 20px;
        }

        @media (max-width: 900px) {
            form {
                grid-template-columns: 1fr;
            }

            .page {
                padding: 18px;
            }

            .table-card {
                overflow-x: auto;
            }
        }
    </style>
</head>

<body>
<x-page-help title="Kundenverwaltung">Hier suchst und pflegst du Kundenstammdaten. Suchen und Anzeigen ist lesend. Neu anlegen, Bearbeiten sowie Änderungen an Ansprechpartnern oder Branchen schreiben Kundendaten. Vor dem Speichern Kundennummer, Anschrift und Zuordnungen prüfen.</x-page-help>


<div class="page">

    <div class="topbar">

        <h1>Kunden</h1>

        <div class="actions">

            <a href="{{ route('kunden.create') }}">
                Neuer Kunde
            </a>

            <a href="{{ route('dashboard') }}">
                Hauptmenü
            </a>

            <a href="{{ route('frontend.switch', 'classic') }}">
                ← Klassische Ansicht
            </a>

        </div>

    </div>

    <div class="search-card">

        <form method="GET">

            <input
                type="text"
                name="q"
                value="{{ request('q') }}"
                placeholder="Kundennummer, Name, Ort, PLZ, E-Mail, Telefon..."
            >

            <select name="status">

                <option value="">
                    Alle Kunden
                </option>

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

            <a class="secondary" href="{{ route('kunden.index') }}">
                Zurücksetzen
            </a>

        </form>

    </div>

    <div class="table-card">

        <table>

            <thead>
            <tr>
                <th>Nr.</th>
                <th>Kunde</th>
                <th>Ort</th>
                <th>Telefon</th>
                <th>E-Mail</th>
                <th>Status</th>
            </tr>
            </thead>

            <tbody>

            @forelse ($kunden as $kunde)

                <tr>

                    <td>
                        {{ $kunde->intID }}
                    </td>

                    <td>
                        <a href="{{ route('kunden.show', $kunde->intID) }}">
                            <strong>{{ $kunde->strName }}</strong>
                        </a>
                    </td>

                    <td>
                        {{ $kunde->strPLZ }}
                        {{ $kunde->strOrt }}
                    </td>

                    <td>
                        {{ $kunde->strTelefon }}
                    </td>

                    <td>
                        @if ($kunde->strEmail)
                            <a href="mailto:{{ $kunde->strEmail }}">
                                {{ $kunde->strEmail }}
                            </a>
                        @endif
                    </td>

                    <td>
                        <span class="badge">
                            {{ $kunde->boolAktiverKunde ? 'Aktiv' : 'Inaktiv' }}
                        </span>
                    </td>

                </tr>

            @empty

                <tr>
                    <td colspan="6">
                        Keine Kunden gefunden.
                    </td>
                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

    <div class="pagination">
        {{ $kunden->links() }}
    </div>

</div>

<script src="{{ asset('js/db-window-manager.js') }}"></script></body>
</html>
