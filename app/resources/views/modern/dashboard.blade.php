<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>tops.net Buchhaltung</title>

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
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .title {
            font-size: 30px;
            font-weight: 700;
        }

        .switch-link {
            color: #4b5563;
            text-decoration: none;
            font-size: 14px;
        }

        .switch-link:hover {
            text-decoration: underline;
        }

        .top-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
        .doc-button { display:inline-block; padding:8px 11px; border-radius:7px; background:#fff; box-shadow:0 1px 4px rgba(0,0,0,.10); color:#075985; text-decoration:none; font-size:13px; }
        .doc-button:hover { background:#eef6fb; }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }

        .card h2 {
            margin-top: 0;
            font-size: 18px;
        }

        .menu-link {
            display: block;
            padding: 10px 0;
            color: #075985;
            text-decoration: none;
        }

        .menu-link:hover {
            text-decoration: underline;
        }

        .disabled {
            color: #9ca3af;
            padding: 10px 0;
        }

        @media (max-width: 900px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .page {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<div class="page">

    <div class="topbar">
        <div class="title">
            tops.net Buchhaltung
        </div>

        <div class="top-actions">
            <a class="doc-button" href="{{ route('documentation.migration') }}" target="_blank" rel="noopener">Technische Doku</a>
            <a class="doc-button" href="{{ route('documentation.handbook') }}" target="_blank" rel="noopener">Benutzerhandbuch</a>
            <a class="doc-button" href="{{ route('documentation.invoice-handbook') }}" target="_blank" rel="noopener">Handbuch Rechnungstool</a>
            <a class="doc-button" href="{{ route('documentation.sql-wiki') }}" target="_blank" rel="noopener">SQL-Statement-Wiki</a>
            @if($adCanAdmin ?? false)<a class="doc-button" href="{{ route('admin.index') }}">Administration</a>@endif
            <a class="switch-link" href="{{ route('frontend.switch', 'classic') }}">← Zur klassischen Ansicht</a>
        </div>
    </div>

    <div class="grid">

        <div class="card">
            <h2>Kunden</h2>

            <a class="menu-link" href="{{ route('kunden.index') }}">
                Kunden suchen
            </a>

            <a class="menu-link" href="{{ route('kunden.index') }}">
                Kundenverwaltung
            </a>
        </div>

        <div class="card">
            <h2>Buchhaltung</h2>

            <a class="menu-link" href="{{ route('rechnungslauf.index') }}">Export Rechnungslauf (XLSX)</a>

            @if($adCanInvoiceTool ?? false)
                <a class="menu-link" href="{{ route('fakturierung.index') }}">Rechnungstool starten</a>
            @else
                <div class="disabled" title="Erfordert DB-Webapp-Rechnungstool">Rechnungstool starten</div>
            @endif

            <a class="menu-link" href="{{ route('lastschriften.index') }}">Lastschriften bezahlt markieren</a>

            <a class="menu-link" href="{{ route('rechnungen.index') }}">
                Rechnungen
            </a>

            <a class="menu-link" href="{{ route('auftraege.index') }}">
                Auftragsverwaltung
            </a>
            <a class="menu-link" href="{{ route('wiedervorlagen.index') }}">
                Aufträge-Wiedervorlage
            </a>
            <a class="menu-link" href="{{ route('fremdaccounting.index') }}">
                Fremdaccounting
            </a>

            <a class="menu-link" href="{{ route('datev.index') }}">
                DATEV und Bilanzen
            </a>

            <a class="menu-link" href="{{ route('rechnungen-ohne-ust.index') }}">
                Rechnungen ohne USt...
            </a>
        </div>

        <div class="card">
            <h2>Domains</h2>

            <a class="menu-link" href="{{ route('domain-customer.index') }}">
                Kundenzuordnung
            </a>

            <a class="menu-link" href="{{ route('domain-order.index') }}">
                Zur Auftragsposition
            </a>

            <div class="disabled" title="Bewusst nicht migriert; spätere Domainverwaltung über die DENIC-API siehe Dokumentation.">
                Aktuelle Domain-Aufträge
            </div>

            <a class="menu-link" href="{{ route('domain-eintraege.index') }}">
                Domain-Einträge bearbeiten
            </a>

            <a class="menu-link" href="{{ route('domain-create.create') }}">
                Domain eintragen
            </a>

            <a class="menu-link" href="{{ route('ipv4-reverse.index') }}">
                IPv4 Reverse
            </a>
        </div>

        <div class="card">
            <h2>Vertrieb</h2>

            <a class="menu-link" href="{{ route('branchen-auswertung.index') }}">
                Zugeordnete Branchen
            </a>

            <a class="menu-link" href="{{ route('branchen-auswertung.export') }}" download>
                Branchen als XLSX exportieren
            </a>
        </div>

        <div class="card">
            <h2>Administration</h2>

            <div class="disabled">
                Zahlungsarten
            </div>

            <a class="menu-link" href="{{ route('produkte.index') }}">
                Produkte
            </a>

            <a class="menu-link" href="{{ route('staffelgruppen.index') }}">
                Staffelgruppen (#1)
            </a>

            <a class="menu-link" href="{{ route('linearstaffeln.index') }}">
                Linearstaffeln (#2)
            </a>

            <a class="menu-link" href="{{ route('bandbreitentarife.index') }}">
                Abrechnungsart #4/#7 · Bandbreiten-Tarife bearbeiten (MAX oder SUM)
            </a>
            <a class="menu-link" href="{{ route('bereichsstaffeln.index') }}">
                Bereichsstaffeln (#6)
            </a>

            <a class="menu-link" href="{{ route('zeittarife.index') }}">
                Zeittarife (#3)
            </a>

            <a class="menu-link" href="{{ route('domainkonditionen.index') }}">
                Abrechnungsart #5 · Domainkonditionen bearbeiten
            </a>

            <a class="menu-link" href="{{ route('sms-zugaenge.index') }}">
                SMS-Zugänge
            </a>
        </div>

        <div class="card">
            <h2>Technik</h2>

            <a class="menu-link" href="{{ route('anbindungen.index') }}">
                Verbindungen · Anbindungen und Accounting-Zuordnung pflegen
            </a>

            <a class="menu-link" href="{{ route('netze.index') }}">
                Netze · IP-Netze pflegen
            </a>

            <a class="menu-link" href="{{ route('ports.index') }}">
                Ports · Port-Accounting pflegen
            </a>

            <a class="menu-link" href="{{ route('dialins.index') }}">
                Dialins · Radius-Zugänge pflegen
            </a>
        </div>

    </div>

</div>

<script src="{{ asset('js/db-window-manager.js') }}"></script></body>
</html>
