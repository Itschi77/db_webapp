<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Die große tops.net Buchhaltung</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            font-size: 14px;
            background: #d9d9d9;
            color: #111;
        }

        .window {
            max-width: 1220px;
            margin: 0 auto;
            background: #efefef;
            border: 1px solid #8c8c8c;
            box-shadow: 0 2px 8px rgba(0,0,0,.18);
        }

        .titlebar {
            background: #f7f7f7;
            border-bottom: 1px solid #aaa;
            padding: 9px 12px;
            font-size: 16px;
            font-weight: 600;
        }

        .content {
            padding: 14px;
        }

        .top-grid {
            display: grid;
            grid-template-columns: 1fr 1.75fr;
            gap: 12px;
        }

        .lower-grid {
            display: grid;
            grid-template-columns: 1.55fr 1fr;
            gap: 12px;
            margin-top: 12px;
        }

        fieldset {
            border: 1px solid #9c9c9c;
            margin: 0;
            padding: 10px;
            min-width: 0;
            background: #efefef;
        }

        legend {
            padding: 0 5px;
            font-size: 13px;
        }

        .button-grid {
            display: grid;
            gap: 8px;
        }

        .button-grid.one {
            grid-template-columns: 1fr;
        }

        .button-grid.two {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .button-grid.three {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .menu-button {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 8px 10px;

            border: 1px solid #8b8b8b;
            border-top-color: #fff;
            border-left-color: #fff;
            border-right-color: #666;
            border-bottom-color: #666;

            background: #f2f2f2;
            color: #111;
            text-decoration: none;
            text-align: center;
            line-height: 1.2;

            box-shadow:
                inset 1px 1px 0 #fff,
                inset -1px -1px 0 #c7c7c7;

            cursor: pointer;
        }

        .menu-button:hover {
            background: #e8e8e8;
        }

        .menu-button:active {
            border-top-color: #666;
            border-left-color: #666;
            border-right-color: #fff;
            border-bottom-color: #fff;

            box-shadow:
                inset 1px 1px 0 #bdbdbd;
        }

        .menu-button.disabled {
            color: #777;
            background: #e6e6e6;
            cursor: not-allowed;
            opacity: .75;
        }

        .section-gap {
            margin-top: 12px;
        }

        .menu-help-item{position:relative;min-width:0}
        .menu-help-item>.menu-button{width:100%;height:100%;padding-right:34px}
        .menu-help-q{position:absolute;right:7px;top:7px;z-index:5;width:19px;height:19px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#dfe8f2;color:#184d7a;font:bold 12px Arial;cursor:help;outline:none;border:1px solid #b8c9da}
        .menu-help-text{display:none;position:absolute;right:4px;top:31px;z-index:100;width:min(330px,75vw);padding:10px 11px;background:#fff;color:#1f2937;border:1px solid #aebdcb;border-radius:7px;box-shadow:0 7px 22px #0003;text-align:left;font:12px/1.4 "Segoe UI",Arial,sans-serif}
        .menu-help-q:hover+.menu-help-text,.menu-help-q:focus+.menu-help-text{display:block}

        .doc-rail {
            position: fixed;
            left: 10px;
            top: 92px;
            width: 155px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 50;
        }

        .doc-rail a {
            display: block;
            padding: 9px 10px;
            border: 1px solid #8b8b8b;
            border-top-color: #fff;
            border-left-color: #fff;
            border-right-color: #666;
            border-bottom-color: #666;
            background: #f2f2f2;
            color: #111;
            text-decoration: none;
            text-align: center;
            line-height: 1.2;
            box-shadow: inset 1px 1px 0 #fff, inset -1px -1px 0 #c7c7c7;
        }

        .doc-rail a:hover { background: #e8e8e8; }
        .doc-rail .menu-help-item{width:100%}.doc-rail .menu-help-item>a{width:100%;padding-right:30px}.doc-rail .menu-help-q{right:5px;top:50%;transform:translateY(-50%)}.doc-rail .menu-help-text{right:0;top:36px;transform:none}

        @media (max-width: 1380px) {
            .doc-rail { position: static; width: auto; flex-direction: row; margin: 0 auto 12px; max-width: 1220px; }
            .doc-rail a { flex: 1; }
        }

        .footer {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 14px;
            padding-top: 10px;
            border-top: 1px solid #aaa;
            font-size: 12px;
        }

        .exit-button {
            width: 42px;
            min-height: 38px;
            font-size: 18px;
        }

        @media (max-width: 900px) {
            .top-grid,
            .lower-grid {
                grid-template-columns: 1fr;
            }

            .button-grid.three {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 600px) {
            body {
                padding: 8px;
            }

            .button-grid.two,
            .button-grid.three {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="doc-rail" aria-label="Dokumentation">
    <div class="menu-help-item"><a href="{{ route('documentation.migration') }}" target="_blank" rel="noopener">Technische Doku</a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Technische Doku">?</span><span class="menu-help-text">Öffnet die technische Projektdokumentation mit Architektur, Migration, Infrastruktur und Betriebsdetails. Rein lesend.</span></div>
    <div class="menu-help-item"><a href="{{ route('documentation.handbook') }}" target="_blank" rel="noopener">Benutzerhandbuch</a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Benutzerhandbuch">?</span><span class="menu-help-text">Öffnet das allgemeine Benutzerhandbuch der Webanwendung. Rein lesend.</span></div>
    <div class="menu-help-item"><a href="{{ route('documentation.invoice-handbook') }}" target="_blank" rel="noopener">Handbuch Rechnungstool</a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Handbuch Rechnungstool">?</span><span class="menu-help-text">Öffnet die Bedienungsanleitung für Rechnungstool, E-Rechnung und Mahnwesen. Rein lesend.</span></div>
    @if($adCanAdmin ?? false)<div class="menu-help-item"><a href="{{ route('admin.index') }}">Administration</a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Administration">?</span><span class="menu-help-text">Öffnet die Systemadministration für Verbindungen, Backups, Systemtests, Logs und Notfallstatus. Änderungen dort können die Anwendungskonfiguration beeinflussen.</span></div>@endif
    <div class="menu-help-item"><a href="{{ route('documentation.sql-wiki') }}" target="_blank" rel="noopener">SQL-Statement-Wiki</a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu SQL-Statement-Wiki">?</span><span class="menu-help-text">Öffnet bestätigte SQL-Abfragen, Rechte-Statements und technische Hinweise. Rein lesend.</span></div>
</div>

<div class="window">

    <div class="titlebar">
        Die große tops.net Buchhaltung
    </div>

    <div class="content">

        <div class="top-grid">

            <fieldset>
                <legend>Allgemein / Accounting</legend>

                <div class="button-grid one">

                    <div class="menu-help-item"><a class="menu-button" href="{{ route('kunden.index') }}">
                        Kunde nach Kundennummer suchen
                    </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Kunde nach Kundennummer suchen">?</span><span class="menu-help-text">Öffnet die Kundensuche. Kundennummer eingeben oder in der Kundenliste suchen. Die Suche selbst verändert keine Daten.</span></div>

                    <div class="menu-help-item"><a class="menu-button" href="{{ route('kunden.index') }}">
                        Kunde nach Namen (auch Teilnamen) suchen
                    </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Kunde nach Namen (auch Teilnamen) suchen">?</span><span class="menu-help-text">Öffnet die Kundensuche. Es kann nach vollständigem Namen oder Namensbestandteilen gesucht werden. Die Suche selbst ist rein lesend.</span></div>

                    <div class="menu-help-item"><a class="menu-button" href="{{ route('fremdaccounting.index') }}">
                        Auswertung der Fremd-Accountings
                    </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Auswertung der Fremd-Accountings">?</span><span class="menu-help-text">Zeigt Fremd-Accountingdaten und deren Auswertung. Die Auswertung ist zur Kontrolle gedacht und verändert keine Rohdaten.</span></div>


                </div>
            </fieldset>

            <fieldset>
                <legend>Buchhaltung</legend>

                <div class="button-grid three">

                    <div class="menu-help-item"><a class="menu-button" href="{{ route('kunden.index') }}">
                        Kundenverwaltung
                    </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Kundenverwaltung">?</span><span class="menu-help-text">Öffnet Kundenstammdaten und Ansprechpartner. Anzeigen ist lesend; Bearbeiten oder Anlegen verändert Kundendaten.</span></div>

                    <div class="menu-help-item"><a class="menu-button" href="{{ route('auftraege.index') }}">
                        Auftragsverwaltung
                    </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Auftragsverwaltung">?</span><span class="menu-help-text">Öffnet Aufträge und Auftragspositionen. Anzeigen ist lesend; Änderungen an Aufträgen oder Positionen wirken auf spätere Abrechnungen.</span></div>

                    <div class="menu-help-item"><a class="menu-button" href="{{ route('rechnungslauf.index') }}">
                        Export Rechnungslauf (XLSX)
                    </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Export Rechnungslauf (XLSX)">?</span><span class="menu-help-text">Erstellt einen Excel-Export des Rechnungslaufs für den gewählten Zeitraum. Der Export liest Daten und verändert keine Rechnungen.</span></div>

                    <div class="menu-help-item"><a class="menu-button" href="{{ route('rechnungen.index') }}">
                        Rechnungen
                    </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Rechnungen">?</span><span class="menu-help-text">Öffnet vorhandene Rechnungen mit Suche, Detailansicht und Dokumentpfaden. Reines Anzeigen verändert keine Daten.</span></div>

                    <div class="menu-help-item"><a class="menu-button" href="{{ route('datev.index') }}">
                        DATEV und Bilanzen...
                    </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu DATEV und Bilanzen...">?</span><span class="menu-help-text">Öffnet die DATEV-/Bilanzfunktionen und Prüfungen zu Konten, Produkten und Rechnungen. Exporte sind lesend; Pflegeaktionen können Zuordnungen ändern.</span></div>

                    <div class="menu-help-item"><a class="menu-button" href="{{ route('lastschriften.index') }}">
                        Lastschriften bezahlt markieren
                    </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Lastschriften bezahlt markieren">?</span><span class="menu-help-text">Dient dazu, Lastschrift-Rechnungen als bezahlt zu markieren. Diese Funktion verändert Zahlungsstatus und sollte nur mit eindeutig bestätigten Zahlungseingängen verwendet werden.</span></div>

                    <div class="menu-help-item"><a class="menu-button" href="{{ route('wiedervorlagen.index') }}">
                        Aufträge-WV
                    </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Aufträge-WV">?</span><span class="menu-help-text">Zeigt Aufträge, deren Wiedervorlage fällig ist. Die Liste ist zur Bearbeitung offener Vorgänge gedacht.</span></div>

                    @if($adCanInvoiceTool ?? false)
                        <div class="menu-help-item"><a class="menu-button" href="{{ route('fakturierung.index') }}">
                            Rechnungstool starten
                        </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Rechnungstool starten">?</span><span class="menu-help-text">Öffnet Fakturierung, Vorschau, E-Rechnung und Mahnwesen. Produktive Rechnungserzeugung ist zusätzlich serverseitig geschützt und erfordert besondere Berechtigung.</span></div>
                    @else
                        <div class="menu-help-item"><span class="menu-button disabled" title="Erfordert DB-Webapp-Rechnungstool">
                            Rechnungstool starten
                        </span><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Rechnungstool starten">?</span><span class="menu-help-text">Öffnet Fakturierung, Vorschau, E-Rechnung und Mahnwesen. Produktive Rechnungserzeugung ist zusätzlich serverseitig geschützt und erfordert besondere Berechtigung.</span></div>
                    @endif

                    <div class="menu-help-item"><a class="menu-button" href="{{ route('rechnungen-ohne-ust.index') }}">
                        Rechnungen ohne USt...
                    </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Rechnungen ohne USt...">?</span><span class="menu-help-text">Zeigt Rechnungen ohne Umsatzsteuer zur Prüfung. Änderungen oder Korrekturen sollten nur nach fachlicher Klärung erfolgen.</span></div>

                </div>
            </fieldset>

        </div>

        <div class="lower-grid">

            <div>

                <fieldset>
                    <legend>Vertrieb</legend>

                    <div class="button-grid two">

                        <div class="menu-help-item"><a class="menu-button" href="{{ route('branchen-auswertung.index') }}">
                            Zugeordnete Branchen
                        </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Zugeordnete Branchen">?</span><span class="menu-help-text">Zeigt die aktuell Kunden zugeordneten Branchen. Die Übersicht dient Kontrolle und Vertriebsauswertung.</span></div>

                        <div class="menu-help-item"><a class="menu-button" href="{{ route('branchen-auswertung.export') }}" download>
                            Zugeordnete Branchen exportieren
                        </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Zugeordnete Branchen exportieren">?</span><span class="menu-help-text">Exportiert die Branchenzuordnungen als XLSX. Der Export verändert keine Daten.</span></div>

                    </div>
                </fieldset>

                <fieldset class="section-gap">
                    <legend>Buchhaltungs-Admins</legend>

                    <div class="button-grid two">

                        <div class="menu-help-item"><a class="menu-button" href="{{ route('staffelgruppen.index') }}">
                            Abrechnungsart #1:<br>
                            Staffelgruppen bearbeiten
                        </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Abrechnungsart #1: Staffelgruppen bearbeiten">?</span><span class="menu-help-text">Pflegt Staffelgruppen für Abrechnungsart 1. Änderungen wirken auf spätere Staffelberechnungen und sollten fachlich geprüft werden.</span></div>

                        <div class="menu-help-item"><a class="menu-button" href="{{ route('linearstaffeln.index') }}">
                            Abrechnungsart #2:<br>
                            Linear-Staffeln bearbeiten
                        </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Abrechnungsart #2: Linear-Staffeln bearbeiten">?</span><span class="menu-help-text">Pflegt Linearstaffeln für Abrechnungsart 2. Änderungen beeinflussen spätere Abrechnungsbeträge.</span></div>

                        <div class="menu-help-item"><a class="menu-button" href="{{ route('zeittarife.index') }}">
                            Abrechnungsart #3:<br>
                            Zeittarife bearbeiten
                        </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Abrechnungsart #3: Zeittarife bearbeiten">?</span><span class="menu-help-text">Pflegt Zeittarife für Abrechnungsart 3. Änderungen wirken auf zeitabhängige Abrechnungen.</span></div>

                        <div class="menu-help-item"><a class="menu-button" href="{{ route('bereichsstaffeln.index') }}">
                            Abrechnungsart #6:<br>
                            Bereichsstaffeln bearbeiten
                        </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Abrechnungsart #6: Bereichsstaffeln bearbeiten">?</span><span class="menu-help-text">Pflegt Bereichsstaffeln für Abrechnungsart 6. Änderungen wirken auf spätere Bereichsberechnungen.</span></div>

                        <div class="menu-help-item"><a class="menu-button" href="{{ route('bandbreitentarife.index') }}">
                            Abrechnungsart #4/#7:<br>
                            Bandbreiten-Tarife bearbeiten<br>
                            (MAX oder SUM)
                        </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Abrechnungsart #4/#7: Bandbreiten-Tarife bearbeiten (MAX oder SUM)">?</span><span class="menu-help-text">Pflegt Bandbreiten-Tarife für Abrechnungsarten 4 und 7. MAX und SUM bestimmen die Berechnungslogik; Änderungen wirken auf spätere Rechnungen.</span></div>

                        <div class="menu-help-item"><a class="menu-button" href="{{ route('sms-zugaenge.index') }}">
                            SMS-Zugänge pflegen
                        </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu SMS-Zugänge pflegen">?</span><span class="menu-help-text">Pflegt SMS-Zugänge und deren Zuordnung. Änderungen wirken auf die technische Nutzung dieser Zugänge.</span></div>

                        <div class="menu-help-item"><a class="menu-button" href="{{ route('domainkonditionen.index') }}">
                            Abrechnungsart #5:<br>
                            Domainkonditionen bearbeiten
                        </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Abrechnungsart #5: Domainkonditionen bearbeiten">?</span><span class="menu-help-text">Pflegt Domainpreise und Konditionen für Abrechnungsart 5. Änderungen wirken auf spätere Domainabrechnungen.</span></div>

                        <div class="menu-help-item"><a class="menu-button" href="{{ route('produkte.index') }}">
                            Produkte pflegen
                        </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Produkte pflegen">?</span><span class="menu-help-text">Pflegt Produktstammdaten und abrechnungsrelevante Zuordnungen. Änderungen können spätere Aufträge und Rechnungen beeinflussen.</span></div>

                    </div>
                </fieldset>

            </div>

            <div>

                <fieldset>
                    <legend>Zuordnung von Domains</legend>

                    <div class="button-grid two">

                        <div class="menu-help-item"><a class="menu-button" href="{{ route('domain-customer.index') }}">
                            zum Kunden
                        </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu zum Kunden">?</span><span class="menu-help-text">Ordnet Domains einem Kunden zu bzw. zeigt die Kundenzuordnung. Änderungen beeinflussen, welchem Kunden eine Domain zugeordnet ist.</span></div>

                        <div class="menu-help-item"><a class="menu-button" href="{{ route('domain-order.index') }}">
                            zur Auftragsposition
                        </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu zur Auftragsposition">?</span><span class="menu-help-text">Ordnet Domains einer konkreten Auftragsposition zu. Änderungen wirken auf die abrechnungsrelevante Zuordnung.</span></div>

                        <div class="menu-help-item"><span class="menu-button disabled" title="Bewusst nicht migriert; spätere Domainverwaltung über die DENIC-API siehe Dokumentation.">
                            Aktuelle Domain-Aufträge
                        </span><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Aktuelle Domain-Aufträge">?</span><span class="menu-help-text">Diese Alt-Funktion ist bewusst nicht migriert. Die spätere Domainverwaltung soll über die vorgesehene DENIC-API erfolgen.</span></div>

                        <div class="menu-help-item"><span class="menu-button disabled">
                            Look up starten
                        </span><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Look up starten">?</span><span class="menu-help-text">Diese Alt-Funktion ist derzeit nicht verfügbar. Sie bleibt nur sichtbar, damit die frühere Oberfläche vollständig nachvollziehbar bleibt.</span></div>

                        <div class="menu-help-item"><a class="menu-button" href="{{ route('domain-eintraege.index') }}">
                            Domain-Einträge bearbeiten
                        </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Domain-Einträge bearbeiten">?</span><span class="menu-help-text">Pflegt vorhandene Domain-Einträge. Änderungen wirken auf Domainstammdaten und sollten nur bei eindeutiger Zuordnung durchgeführt werden.</span></div>

                        <span></span>

                        <div class="menu-help-item"><span class="menu-button disabled">
                            Handles pflegen
                        </span><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Handles pflegen">?</span><span class="menu-help-text">Diese historische Funktion ist derzeit nicht migriert und kann nicht verwendet werden.</span></div>

                        <div class="menu-help-item"><span class="menu-button disabled">
                            Owner pflegen
                        </span><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Owner pflegen">?</span><span class="menu-help-text">Diese historische Funktion ist derzeit nicht migriert und kann nicht verwendet werden.</span></div>

                    </div>
                </fieldset>

                <fieldset class="section-gap">
                    <legend>Techniker</legend>

                    <div class="button-grid two">

                        <div class="menu-help-item"><a class="menu-button" href="{{ route('anbindungen.index') }}">
                            Verbindungen pflegen
                        </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Verbindungen pflegen">?</span><span class="menu-help-text">Pflegt Anbindungen und Accounting-Zuordnungen. Änderungen können technische Erfassung und spätere Abrechnung beeinflussen.</span></div>

                        <div class="menu-help-item"><a class="menu-button" href="{{ route('domain-create.create') }}">
                            Domain eintragen
                        </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Domain eintragen">?</span><span class="menu-help-text">Legt einen neuen Domain-Eintrag an. Vor dem Speichern Kunden-/Auftragszuordnung und Schreibweise sorgfältig prüfen.</span></div>

                        <div class="menu-help-item"><a class="menu-button" href="{{ route('netze.index') }}">
                            Netze pflegen
                        </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Netze pflegen">?</span><span class="menu-help-text">Pflegt IP-Netze. Änderungen wirken auf technische Zuordnungen und gegebenenfalls Accounting.</span></div>

                        <div class="menu-help-item"><a class="menu-button" href="{{ route('ports.index') }}">
                            Ports pflegen
                        </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Ports pflegen">?</span><span class="menu-help-text">Pflegt Ports und Port-Accounting-Zuordnungen. Änderungen können die spätere Accounting-Auswertung beeinflussen.</span></div>

                        <div class="menu-help-item"><a class="menu-button" href="{{ route('dialins.index') }}">
                            Dialins pflegen
                        </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Dialins pflegen">?</span><span class="menu-help-text">Pflegt Radius-/Dialin-Zugänge. Änderungen wirken auf technische Zugangsdaten und Zuordnungen.</span></div>

                        <div class="menu-help-item"><a class="menu-button" href="{{ route('ipv4-reverse.index') }}">
                            IPv4 Reverse
                        </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu IPv4 Reverse">?</span><span class="menu-help-text">Verwaltet Reverse-DNS-Einträge für IPv4. Änderungen wirken auf technische DNS-Zuordnungen.</span></div>

                    </div>
                </fieldset>

            </div>

        </div>

<div class="footer">

    <div class="menu-help-item"><button
        class="menu-button exit-button"
        type="button"
        onclick="history.back()"
        title="Zurück"
    >
        ↩
    </button><span class="menu-help-q" tabindex="0" aria-label="Hilfe zu Zurück">?</span><span class="menu-help-text">Kehrt zur vorherigen Browseransicht zurück. Es werden keine Daten verändert.</span></div>

    <span>
        © bh2000 · tops.net GmbH &amp; Co. KG · Web-Migration
    </span>

    <span style="margin-left:auto;">
        <div class="menu-help-item"><a href="{{ route('frontend.switch', 'modern') }}">
            Zum neuen Frontend wechseln →
        </a><span class="menu-help-q" tabindex="0" aria-label="Hilfe zum Ansichtswechsel">?</span><span class="menu-help-text">Wechselt nur auf das neue Hauptmenü. Fachliche Daten und Berechtigungen bleiben unverändert.</span></div>
    </span>

</div>
    </div>

</div>

<script src="{{ asset('js/db-window-manager.js') }}?v=20260918-2"></script></body>
</html>
