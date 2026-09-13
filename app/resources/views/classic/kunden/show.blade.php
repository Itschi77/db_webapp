<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Kundenverwaltung - {{ $kunde->strName }}</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 12px;
            font-family: Arial, sans-serif;
            font-size: 13px;
            background: #d9d9d9;
            color: #111;
        }

        .window {
            max-width: 1280px;
            margin: auto;
            background: #efefef;
            border: 1px solid #888;
            padding: 14px;
        }

        .topbar {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 12px;
        }

        .topbar strong {
            font-size: 14px;
        }

        .customer-number {
            width: 100px;
            padding: 5px;
            background: #ddd;
            border: 1px solid #888;
            text-align: right;
        }

        .section-title {
            color: #0000cc;
            font-weight: bold;
            border-bottom: 2px solid #0000cc;
            margin: 14px 0 8px;
            padding-bottom: 3px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 32px;
        }

        .field {
            display: grid;
            grid-template-columns: 155px 1fr;
            align-items: center;
            gap: 8px;
        }

        .value {
            min-height: 27px;
            padding: 5px 7px;
            background: white;
            border: 1px solid #999;
        }

        .checkbox {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .check {
            display: inline-flex;
            width: 16px;
            height: 16px;
            border: 1px solid #777;
            background: white;
            justify-content: center;
            align-items: center;
            font-weight: bold;
        }

        .wide {
            grid-column: 1 / -1;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .textarea {
            min-height: 90px;
            padding: 7px;
            background: white;
            border: 1px solid #999;
            white-space: pre-wrap;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th,
        td {
            border: 1px solid #aaa;
            padding: 6px 8px;
            text-align: left;
        }

        th {
            background: #ddd;
        }

        .buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 14px;
        }

        .button {
            display: inline-block;
            padding: 7px 12px;
            border: 1px solid #777;
            background: #eee;
            color: #111;
            text-decoration: none;
        }

        .button.disabled {
            color: #888;
            cursor: not-allowed;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            margin-top: 16px;
            padding-top: 10px;
            border-top: 1px solid #999;
        }

        @media (max-width: 900px) {
            .grid,
            .info-grid {
                grid-template-columns: 1fr;
            }

            .field {
                grid-template-columns: 130px 1fr;
            }
        }
    </style>
</head>

<body>

<div class="window">

    <div class="topbar">

        <strong>Kundennummer:</strong>

        <div class="customer-number">
            {{ $kunde->intID }}
        </div>

        <strong style="margin-left:auto;">
            Kundenverwaltung
        </strong>

    </div>

    <div class="section-title">
        Informationen über den Kunden (Auftraggeber)
    </div>

    <div class="grid">

        <div class="field">
            <div>Firma?</div>
            <div class="checkbox">
                <span class="check">
                    {{ $kunde->boolIstFirma ? '✓' : '' }}
                </span>
                Ja
            </div>
        </div>

        <div class="field">
            <div>Kunde aktiv?</div>
            <div class="checkbox">
                <span class="check">
                    {{ $kunde->boolAktiverKunde ? '✓' : '' }}
                </span>
                Ja
            </div>
        </div>

        <div class="field">
            <div>Anrede:</div>
            <div class="value">{{ $kunde->strAnrede }}</div>
        </div>

        <div class="field">
            <div>Ort:</div>
            <div class="value">{{ $kunde->strOrt }}</div>
        </div>

        <div class="field">
            <div>Name:</div>
            <div class="value">{{ $kunde->strName }}</div>
        </div>

        <div class="field">
            <div>PLZ:</div>
            <div class="value">{{ $kunde->strPLZ }}</div>
        </div>

        <div class="field">
            <div>Straße:</div>
            <div class="value">{{ $kunde->strStrasse }}</div>
        </div>

        <div class="field">
            <div>Telefon:</div>
            <div class="value">{{ $kunde->strTelefon }}</div>
        </div>

        <div class="field">
            <div>FAX:</div>
            <div class="value">{{ $kunde->strTelefax }}</div>
        </div>

        <div class="field">
            <div>Zu Händen:</div>
            <div class="value">{{ $kunde->strZuHaenden }}</div>
        </div>

        <div class="field">
            <div>E-Mail:</div>
            <div class="value">{{ $kunde->strEmail }}</div>
        </div>

        <div class="field">
            <div>Angenommen von:</div>
            <div class="value">{{ $kunde->strAngenommenVon }}</div>
        </div>

<div class="field">
    <div>Geburtsdatum:</div>
    <div class="value">
        {{ $kunde->datGeburtsDatum
            ? \Carbon\Carbon::parse($kunde->datGeburtsDatum)->format('d.m.Y')
            : '' }}
    </div>
</div>

<div class="field">
    <div>Kunde seit:</div>
    <div class="value">
        {{ $kunde->datKundeSeit
            ? \Carbon\Carbon::parse($kunde->datKundeSeit)->format('d.m.Y')
            : '' }}
    </div>
</div>

    </div>

    <div class="section-title">
        Informationen zur Zahlungsart
    </div>

    <div class="grid">

        <div class="field">
            <div>Lastschriftkunde?</div>
            <div class="checkbox">
                <span class="check">
                    {{ $kunde->boolLastschrift ? '✓' : '' }}
                </span>
                Ja
            </div>
        </div>

	<div class="field">
    	   <div>Standard-Zahlungsbedingung:</div>
    	   <div class="value">{{ $kunde->zahlungsbedingung?->strBezeichnung ?? 'Nicht hinterlegt' }}
        </div>
	</div>

        <div class="field wide">
            <div>Zahlungsunfähigkeit?</div>
            <div class="checkbox">
                <span class="check">
                    {{ $kunde->boolInsolventOderBeimRechtsanwalt ? '✓' : '' }}
                </span>

                Kunde ist insolvent oder beim Rechtsanwalt
            </div>
        </div>

        <div class="field wide">
            <div>Kommentar:</div>
            <div class="value">
                {{ $kunde->strGrundInsolventOderRA }}
            </div>
        </div>

    </div>

    <div class="section-title">
        Weiterführende Informationen
    </div>

    <div class="grid">

        <div class="field wide">
            <div>Kundenordner im Intranet:</div>
            <div class="value">
                {{ $kunde->strIntranetFolderPath }}
            </div>
        </div>

        <div class="field">
            <div>DATEV-Kundenkonto:</div>
            <div class="value">{{ $kunde->strDatevKundenKonto }}</div>
        </div>

        <div class="field">
            <div>Offene Mahngebühren:</div>
            <div class="value">
                {{ number_format((float) $kunde->{'fOffeneMahngebühren'}, 2, ',', '.') }} €
            </div>
        </div>

        <div class="field">
            <div>Rahmenvertrag:</div>
            <div class="checkbox">
                <span class="check">
                    {{ $kunde->rahmenvertragda ? '✓' : '' }}
                </span>
            </div>
        </div>

        <div class="field">
            <div>WebDNS erlaubt:</div>
            <div class="checkbox">
                <span class="check">
                    {{ $kunde->bWEBDNSistErlaubt ? '✓' : '' }}
                </span>
            </div>
        </div>

        <div class="field">
            <div>Webfreischaltung:</div>
            <div class="checkbox">
                <span class="check">
                    {{ $kunde->bolwebfreischaltung ? '✓' : '' }}
                </span>
            </div>
        </div>

        <div class="field">
            <div>Vertriebsaktivität erforderlich:</div>
            <div class="checkbox">
                <span class="check">
                    {{ $kunde->boolVertriebsnachfrage ? '✓' : '' }}
                </span>
            </div>
        </div>

    </div>

    <div class="info-grid" style="margin-top:12px;">

        <div>
            <strong>Freies Info Feld</strong>

            <div class="textarea">
                {{ $kunde->txtInfo }}
            </div>
        </div>

        <div>
            <strong>Serviceinfo</strong>

            <div class="textarea">
                {{ $kunde->txtServiceinfo }}
            </div>
        </div>

    </div>

    <div class="section-title">
        Ansprechpartner
    </div>

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

                    <td>{{ $ansprechpartner->strFunktion }}</td>
                    <td>{{ $ansprechpartner->strtel1 }}</td>
                    <td>{{ $ansprechpartner->strtelmobil1 }}</td>
                    <td>{{ $ansprechpartner->stremail1 }}</td>
                </tr>

            @endforeach

            </tbody>
        </table>

    @endif

    <div class="section-title">
        Projekte
    </div>

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

    <div class="buttons">

        <a class="button" href="{{ route('kunden.index') }}">
            Kundenübersicht
        </a>

        <span class="button disabled">
            Neuer Kunde
        </span>

        <span class="button disabled">
            Kunde speichern
        </span>

        <span class="button disabled">
            Rechnungsanschriften
        </span>

        <span class="button disabled">
            Offene Rechnungen
        </span>

        <span class="button disabled">
            Zahlungsbedingungen
        </span>
        <a class="button" href="{{ route('kunden.edit', $kunde->intID) }}">
    	    Kunde bearbeiten
	</a>
    </div>

    <div class="footer">

        <a href="{{ route('dashboard') }}">
            ← Hauptmenü
        </a>

        <a href="{{ route('frontend.switch', 'modern') }}">
            Zum neuen Frontend wechseln →
        </a>

    </div>

</div>

</body>
</html>
