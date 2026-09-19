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

        .menu-help-item{position:relative;display:flex;align-items:center;gap:8px;min-width:0}
        .menu-help-item>.menu-link,.menu-help-item>.disabled,.menu-help-item>.doc-button{flex:1;min-width:0}
        .menu-help-q{position:relative;flex:0 0 auto;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#e7f0f8;color:#075985;font-weight:800;font-size:12px;cursor:help;outline:none}
        .menu-help-text{display:none;position:absolute;right:0;top:28px;z-index:100;width:min(340px,78vw);padding:10px 11px;background:#fff;color:#1f2937;border:1px solid #cbd5e1;border-radius:8px;box-shadow:0 8px 24px #0002;font-size:12px;line-height:1.45}
        .menu-help-q:hover+.menu-help-text,.menu-help-q:focus+.menu-help-text{display:block}

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
            <div class="menu-help-item"><a class="doc-button" href="{{ route('documentation.migration') }}" target="_blank" rel="noopener">Technische Doku</a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Technische Doku">?</span><span class="menu-help-text">Öffnet die technische Projektdokumentation mit Architektur, Migration, Infrastruktur und Betriebsdetails. Rein lesend.</span></div>
            <div class="menu-help-item"><a class="doc-button" href="{{ route('documentation.handbook') }}" target="_blank" rel="noopener">Benutzerhandbuch</a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Benutzerhandbuch">?</span><span class="menu-help-text">Öffnet das allgemeine Benutzerhandbuch der Webanwendung. Rein lesend.</span></div>
            <div class="menu-help-item"><a class="doc-button" href="{{ route('documentation.invoice-handbook') }}" target="_blank" rel="noopener">Handbuch Rechnungstool</a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Handbuch Rechnungstool">?</span><span class="menu-help-text">Öffnet die Bedienungsanleitung für Rechnungstool, E-Rechnung und Mahnwesen. Rein lesend.</span></div>
            <div class="menu-help-item"><a class="doc-button" href="{{ route('documentation.sql-wiki') }}" target="_blank" rel="noopener">SQL-Statement-Wiki</a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu SQL-Statement-Wiki">?</span><span class="menu-help-text">Öffnet bestätigte SQL-Abfragen, Rechte-Statements und technische Hinweise. Rein lesend.</span></div>
            @if($adCanAdmin ?? false)<div class="menu-help-item"><a class="doc-button" href="{{ route('admin.index') }}">Administration</a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Administration">?</span><span class="menu-help-text">Öffnet die Systemadministration für Verbindungen, Backups, Systemtests, Logs und Notfallstatus. Änderungen dort können die Anwendungskonfiguration beeinflussen.</span></div>@endif
            <div class="menu-help-item"><a class="switch-link" href="{{ route('frontend.switch', 'classic') }}">← Zur klassischen Ansicht</a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zum Ansichtswechsel">?</span><span class="menu-help-text">Wechselt nur die Darstellung zurück auf das klassische Hauptmenü. Fachliche Daten und Berechtigungen bleiben unverändert.</span></div>
        </div>
    </div>

    <div class="grid">

        <div class="card">
            <h2>Kunden</h2>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('kunden.index') }}">
                Kunden suchen
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Kunden suchen">?</span><span class="menu-help-text">Öffnet die Kundensuche nach Kundennummer, Name und weiteren Kriterien. Die Suche verändert keine Kundendaten.</span></div>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('kunden.index') }}">
                Kundenverwaltung
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Kundenverwaltung">?</span><span class="menu-help-text">Öffnet Kundenstammdaten und Ansprechpartner. Anzeigen ist lesend; Bearbeiten oder Anlegen verändert Kundendaten.</span></div>
        </div>

        <div class="card">
            <h2>Buchhaltung</h2>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('rechnungslauf.index') }}">Export Rechnungslauf (XLSX)</a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Export Rechnungslauf (XLSX)">?</span><span class="menu-help-text">Erstellt einen Excel-Export des Rechnungslaufs für den gewählten Zeitraum. Der Export liest Daten und verändert keine Rechnungen.</span></div>

            @if($adCanInvoiceTool ?? false)
                <div class="menu-help-item"><a class="menu-link" href="{{ route('fakturierung.index') }}">Rechnungstool starten</a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Rechnungstool starten">?</span><span class="menu-help-text">Öffnet Fakturierung, Vorschau, E-Rechnung und Mahnwesen. Produktive Rechnungserzeugung ist zusätzlich serverseitig geschützt und erfordert besondere Berechtigung.</span></div>
            @else
                <div class="menu-help-item"><div class="disabled" title="Erfordert DB-Webapp-Rechnungstool">Rechnungstool starten</div><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Rechnungstool starten">?</span><span class="menu-help-text">Öffnet Fakturierung, Vorschau, E-Rechnung und Mahnwesen. Produktive Rechnungserzeugung ist zusätzlich serverseitig geschützt und erfordert besondere Berechtigung.</span></div>
            @endif

            <div class="menu-help-item"><a class="menu-link" href="{{ route('lastschriften.index') }}">Lastschriften bezahlt markieren</a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Lastschriften bezahlt markieren">?</span><span class="menu-help-text">Dient dazu, Lastschrift-Rechnungen als bezahlt zu markieren. Diese Funktion verändert Zahlungsstatus und sollte nur mit eindeutig bestätigten Zahlungseingängen verwendet werden.</span></div>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('rechnungen.index') }}">
                Rechnungen
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Rechnungen">?</span><span class="menu-help-text">Öffnet vorhandene Rechnungen mit Suche, Detailansicht und Dokumentpfaden. Reines Anzeigen verändert keine Daten.</span></div>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('auftraege.index') }}">
                Auftragsverwaltung
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Auftragsverwaltung">?</span><span class="menu-help-text">Öffnet Aufträge und Auftragspositionen. Anzeigen ist lesend; Änderungen an Aufträgen oder Positionen wirken auf spätere Abrechnungen.</span></div>
            <div class="menu-help-item"><a class="menu-link" href="{{ route('wiedervorlagen.index') }}">
                Aufträge-Wiedervorlage
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Aufträge-Wiedervorlage">?</span><span class="menu-help-text">Zeigt Aufträge mit fälliger Wiedervorlage, damit offene Vorgänge gezielt weiterbearbeitet werden können.</span></div>
            <div class="menu-help-item"><a class="menu-link" href="{{ route('fremdaccounting.index') }}">
                Fremdaccounting
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Fremdaccounting">?</span><span class="menu-help-text">Zeigt und analysiert Fremd-Accountingdaten. Die Auswertung selbst ist rein lesend.</span></div>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('datev.index') }}">
                DATEV und Bilanzen
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu DATEV und Bilanzen">?</span><span class="menu-help-text">Öffnet die DATEV-/Bilanzfunktionen und Prüfungen zu Konten, Produkten und Rechnungen. Exporte sind lesend; Pflegeaktionen können Zuordnungen ändern.</span></div>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('rechnungen-ohne-ust.index') }}">
                Rechnungen ohne USt...
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Rechnungen ohne USt...">?</span><span class="menu-help-text">Zeigt Rechnungen ohne Umsatzsteuer zur Prüfung. Änderungen oder Korrekturen sollten nur nach fachlicher Klärung erfolgen.</span></div>
        </div>

        <div class="card">
            <h2>Domains</h2>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('domain-customer.index') }}">
                Kundenzuordnung
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Kundenzuordnung">?</span><span class="menu-help-text">Ordnet Domains einem Kunden zu bzw. zeigt die Kundenzuordnung. Änderungen beeinflussen, welchem Kunden eine Domain zugeordnet ist.</span></div>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('domain-order.index') }}">
                Zur Auftragsposition
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Zur Auftragsposition">?</span><span class="menu-help-text">Ordnet Domains einer konkreten Auftragsposition zu. Änderungen wirken auf die abrechnungsrelevante Zuordnung.</span></div>

            <div class="menu-help-item"><div class="disabled" title="Bewusst nicht migriert; spätere Domainverwaltung über die DENIC-API siehe Dokumentation.">
                Aktuelle Domain-Aufträge
            </div><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Aktuelle Domain-Aufträge">?</span><span class="menu-help-text">Diese Alt-Funktion ist bewusst nicht migriert. Die spätere Domainverwaltung soll über die vorgesehene DENIC-API erfolgen.</span></div>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('domain-eintraege.index') }}">
                Domain-Einträge bearbeiten
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Domain-Einträge bearbeiten">?</span><span class="menu-help-text">Pflegt vorhandene Domain-Einträge. Änderungen wirken auf Domainstammdaten und sollten nur bei eindeutiger Zuordnung durchgeführt werden.</span></div>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('domain-create.create') }}">
                Domain eintragen
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Domain eintragen">?</span><span class="menu-help-text">Legt einen neuen Domain-Eintrag an. Vor dem Speichern Kunden-/Auftragszuordnung und Schreibweise sorgfältig prüfen.</span></div>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('ipv4-reverse.index') }}">
                IPv4 Reverse
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu IPv4 Reverse">?</span><span class="menu-help-text">Verwaltet Reverse-DNS-Einträge für IPv4. Änderungen wirken auf technische DNS-Zuordnungen.</span></div>
        </div>

        <div class="card">
            <h2>Vertrieb</h2>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('branchen-auswertung.index') }}">
                Zugeordnete Branchen
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Zugeordnete Branchen">?</span><span class="menu-help-text">Zeigt die aktuell Kunden zugeordneten Branchen. Die Übersicht dient Kontrolle und Vertriebsauswertung.</span></div>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('branchen-auswertung.export') }}" download>
                Branchen als XLSX exportieren
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Branchen als XLSX exportieren">?</span><span class="menu-help-text">Exportiert die Branchenzuordnungen als XLSX. Der Export verändert keine Daten.</span></div>
        </div>

        <div class="card">
            <h2>Administration</h2>

            <div class="menu-help-item"><div class="disabled">
                Zahlungsarten
            </div><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Zahlungsarten">?</span><span class="menu-help-text">Dieser Menüpunkt ist derzeit nicht umgesetzt. Er ist nur als Platzhalter der historischen Oberfläche sichtbar.</span></div>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('produkte.index') }}">
                Produkte
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Produkte">?</span><span class="menu-help-text">Pflegt Produktstammdaten und abrechnungsrelevante Zuordnungen. Änderungen können spätere Aufträge und Rechnungen beeinflussen.</span></div>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('staffelgruppen.index') }}">
                Staffelgruppen (#1)
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Staffelgruppen (#1)">?</span><span class="menu-help-text">Pflegt Staffelgruppen für Abrechnungsart 1. Änderungen wirken auf spätere Staffelberechnungen.</span></div>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('linearstaffeln.index') }}">
                Linearstaffeln (#2)
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Linearstaffeln (#2)">?</span><span class="menu-help-text">Pflegt Linearstaffeln für Abrechnungsart 2. Änderungen beeinflussen spätere Abrechnungsbeträge.</span></div>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('bandbreitentarife.index') }}">
                Abrechnungsart #4/#7 · Bandbreiten-Tarife bearbeiten (MAX oder SUM)
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Abrechnungsart #4/#7 · Bandbreiten-Tarife bearbeiten (MAX oder SUM)">?</span><span class="menu-help-text">Pflegt Bandbreiten-Tarife für Abrechnungsarten 4 und 7. MAX und SUM bestimmen die Berechnungslogik; Änderungen wirken auf spätere Rechnungen.</span></div>
            <div class="menu-help-item"><a class="menu-link" href="{{ route('bereichsstaffeln.index') }}">
                Bereichsstaffeln (#6)
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Bereichsstaffeln (#6)">?</span><span class="menu-help-text">Pflegt Bereichsstaffeln für Abrechnungsart 6. Änderungen wirken auf spätere Bereichsberechnungen.</span></div>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('zeittarife.index') }}">
                Zeittarife (#3)
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Zeittarife (#3)">?</span><span class="menu-help-text">Pflegt Zeittarife für Abrechnungsart 3. Änderungen wirken auf zeitabhängige Abrechnungen.</span></div>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('domainkonditionen.index') }}">
                Abrechnungsart #5 · Domainkonditionen bearbeiten
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Abrechnungsart #5 · Domainkonditionen bearbeiten">?</span><span class="menu-help-text">Pflegt Domainpreise und Konditionen für Abrechnungsart 5. Änderungen wirken auf spätere Domainabrechnungen.</span></div>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('sms-zugaenge.index') }}">
                SMS-Zugänge
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu SMS-Zugänge">?</span><span class="menu-help-text">Pflegt SMS-Zugänge und deren Zuordnung. Änderungen wirken auf die technische Nutzung dieser Zugänge.</span></div>
        </div>

        <div class="card">
            <h2>Technik</h2>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('anbindungen.index') }}">
                Verbindungen · Anbindungen und Accounting-Zuordnung pflegen
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Verbindungen · Anbindungen und Accounting-Zuordnung pflegen">?</span><span class="menu-help-text">Pflegt Anbindungen und Accounting-Zuordnungen. Änderungen können technische Erfassung und spätere Abrechnung beeinflussen.</span></div>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('netze.index') }}">
                Netze · IP-Netze pflegen
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Netze · IP-Netze pflegen">?</span><span class="menu-help-text">Pflegt IP-Netze. Änderungen wirken auf technische Zuordnungen und gegebenenfalls Accounting.</span></div>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('ports.index') }}">
                Ports · Port-Accounting pflegen
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Ports · Port-Accounting pflegen">?</span><span class="menu-help-text">Pflegt Ports und Port-Accounting-Zuordnungen. Änderungen können die spätere Accounting-Auswertung beeinflussen.</span></div>

            <div class="menu-help-item"><a class="menu-link" href="{{ route('dialins.index') }}">
                Dialins · Radius-Zugänge pflegen
            </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Dialins · Radius-Zugänge pflegen">?</span><span class="menu-help-text">Pflegt Radius-/Dialin-Zugänge. Änderungen wirken auf technische Zugangsdaten und Zuordnungen.</span></div>
        </div>

    </div>

</div>

<script src="{{ asset('js/db-window-manager.js') }}?v=20260918-2"></script></body>
</html>
