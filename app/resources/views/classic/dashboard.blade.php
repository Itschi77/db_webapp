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
    <a href="{{ route('documentation.migration') }}" target="_blank" rel="noopener">Technische Doku</a>
    <a href="{{ route('documentation.handbook') }}" target="_blank" rel="noopener">Benutzerhandbuch</a>
    <a href="{{ route('documentation.sql-wiki') }}" target="_blank" rel="noopener">SQL-Statement-Wiki</a>
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

                    <a class="menu-button" href="{{ route('kunden.index') }}">
                        Kunde nach Kundennummer suchen
                    </a>

                    <a class="menu-button" href="{{ route('kunden.index') }}">
                        Kunde nach Namen (auch Teilnamen) suchen
                    </a>

                    <a class="menu-button" href="{{ route('fremdaccounting.index') }}">
                        Auswertung der Fremd-Accountings
                    </a>


                </div>
            </fieldset>

            <fieldset>
                <legend>Buchhaltung</legend>

                <div class="button-grid three">

                    <a class="menu-button" href="{{ route('kunden.index') }}">
                        Kundenverwaltung
                    </a>

                    <a class="menu-button" href="{{ route('auftraege.index') }}">
                        Auftragsverwaltung
                    </a>

                    <a class="menu-button" href="{{ route('rechnungslauf.index') }}">
                        Export Rechnungslauf (XLSX)
                    </a>

                    <a class="menu-button" href="{{ route('rechnungen.index') }}">
                        Rechnungen
                    </a>

                    <a class="menu-button" href="{{ route('datev.index') }}">
                        DATEV und Bilanzen...
                    </a>

                    <a class="menu-button" href="{{ route('lastschriften.index') }}">
                        Lastschriften bezahlt markieren
                    </a>

                    <a class="menu-button" href="{{ route('wiedervorlagen.index') }}">
                        Aufträge-WV
                    </a>

                    <span class="menu-button disabled">
                        Rechnungstool starten
                    </span>

                    <a class="menu-button" href="{{ route('rechnungen-ohne-ust.index') }}">
                        Rechnungen ohne USt...
                    </a>

                </div>
            </fieldset>

        </div>

        <div class="lower-grid">

            <div>

                <fieldset>
                    <legend>Vertrieb</legend>

                    <div class="button-grid two">

                        <a class="menu-button" href="{{ route('branchen-auswertung.index') }}">
                            Zugeordnete Branchen
                        </a>

                        <a class="menu-button" href="{{ route('branchen-auswertung.export') }}" download>
                            Zugeordnete Branchen exportieren
                        </a>

                    </div>
                </fieldset>

                <fieldset class="section-gap">
                    <legend>Buchhaltungs-Admins</legend>

                    <div class="button-grid two">

                        <a class="menu-button" href="{{ route('staffelgruppen.index') }}">
                            Abrechnungsart #1:<br>
                            Staffelgruppen bearbeiten
                        </a>

                        <a class="menu-button" href="{{ route('linearstaffeln.index') }}">
                            Abrechnungsart #2:<br>
                            Linear-Staffeln bearbeiten
                        </a>

                        <a class="menu-button" href="{{ route('zeittarife.index') }}">
                            Abrechnungsart #3:<br>
                            Zeittarife bearbeiten
                        </a>

                        <a class="menu-button" href="{{ route('bereichsstaffeln.index') }}">
                            Abrechnungsart #6:<br>
                            Bereichsstaffeln bearbeiten
                        </a>

                        <a class="menu-button" href="{{ route('bandbreitentarife.index') }}">
                            Abrechnungsart #4/#7:<br>
                            Bandbreiten-Tarife bearbeiten<br>
                            (MAX oder SUM)
                        </a>

                        <a class="menu-button" href="{{ route('sms-zugaenge.index') }}">
                            SMS-Zugänge pflegen
                        </a>

                        <a class="menu-button" href="{{ route('domainkonditionen.index') }}">
                            Abrechnungsart #5:<br>
                            Domainkonditionen bearbeiten
                        </a>

                        <a class="menu-button" href="{{ route('produkte.index') }}">
                            Produkte pflegen
                        </a>

                    </div>
                </fieldset>

            </div>

            <div>

                <fieldset>
                    <legend>Zuordnung von Domains</legend>

                    <div class="button-grid two">

                        <span class="menu-button disabled">
                            zum Kunden
                        </span>

                        <span class="menu-button disabled">
                            zur Auftragsposition
                        </span>

                        <span class="menu-button disabled">
                            Aktuelle Domain-Aufträge
                        </span>

                        <span class="menu-button disabled">
                            Look up starten
                        </span>

                        <span class="menu-button disabled">
                            Domain-Einträge bearbeiten
                        </span>

                        <span></span>

                        <span class="menu-button disabled">
                            Handles pflegen
                        </span>

                        <span class="menu-button disabled">
                            Owner pflegen
                        </span>

                    </div>
                </fieldset>

                <fieldset class="section-gap">
                    <legend>Techniker</legend>

                    <div class="button-grid two">

                        <a class="menu-button" href="{{ route('anbindungen.index') }}">
                            Verbindungen pflegen
                        </a>

                        <span class="menu-button disabled">
                            Domain eintragen
                        </span>

                        <span class="menu-button disabled">
                            Netze pflegen
                        </span>

                        <span class="menu-button disabled">
                            Ports pflegen
                        </span>

                        <span class="menu-button disabled">
                            Dialins pflegen
                        </span>

                        <span class="menu-button disabled">
                            IPv4 Reverse
                        </span>

                    </div>
                </fieldset>

            </div>

        </div>

<div class="footer">

    <button
        class="menu-button exit-button"
        type="button"
        onclick="history.back()"
        title="Zurück"
    >
        ↩
    </button>

    <span>
        © bh2000 · tops.net GmbH &amp; Co. KG · Web-Migration
    </span>

    <span style="margin-left:auto;">
        <a href="{{ route('frontend.switch', 'modern') }}">
            Zum neuen Frontend wechseln →
        </a>
    </span>

</div>
    </div>

</div>

<script src="{{ asset('js/db-window-manager.js') }}"></script></body>
</html>
