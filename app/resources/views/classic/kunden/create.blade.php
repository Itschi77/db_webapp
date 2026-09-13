<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Kundenverwaltung - Neuer Kunde</title>

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

    <form method="POST" action="{{ route('kunden.store') }}">
        @csrf

        <div class="topbar">

            <strong>Kundennummer:</strong>

            <div class="customer-number">
                (Neu)
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
                    <input
                        type="checkbox"
                        name="boolIstFirma"
                        value="1"
                        @checked(old('boolIstFirma', $kunde->boolIstFirma))
                    >
                    Ja
                </div>
            </div>

            <div class="field">
                <div>Kunde aktiv?</div>
                <div class="checkbox">
                    <input
                        type="checkbox"
                        name="boolAktiverKunde"
                        value="1"
                        @checked(old('boolAktiverKunde', $kunde->boolAktiverKunde))
                    >
                    Ja
                </div>
            </div>

            <div class="field">
                <div>Anrede:</div>
                <div>
                    <input
                        type="text"
                        name="strAnrede"
                        value="{{ old('strAnrede', $kunde->strAnrede) }}"
                        style="width:100%; padding:5px;"
                    >
                </div>
            </div>

            <div class="field">
                <div>Ort:</div>
                <div>
                    <input
                        type="text"
                        name="strOrt"
                        value="{{ old('strOrt', $kunde->strOrt) }}"
                        style="width:100%; padding:5px;"
                    >
                </div>
            </div>

            <div class="field">
                <div>Name:</div>
                <div>
                    <input
                        type="text"
                        name="strName"
                        value="{{ old('strName', $kunde->strName) }}"
                        style="width:100%; padding:5px;"
                        required
                    >
                </div>
            </div>

            <div class="field">
                <div>PLZ:</div>
                <div>
                    <input
                        type="text"
                        name="strPLZ"
                        value="{{ old('strPLZ', $kunde->strPLZ) }}"
                        style="width:100%; padding:5px;"
                    >
                </div>
            </div>

            <div class="field">
                <div>Straße:</div>
                <div>
                    <input
                        type="text"
                        name="strStrasse"
                        value="{{ old('strStrasse', $kunde->strStrasse) }}"
                        style="width:100%; padding:5px;"
                    >
                </div>
            </div>

            <div class="field">
                <div>Telefon:</div>
                <div>
                    <input
                        type="text"
                        name="strTelefon"
                        value="{{ old('strTelefon', $kunde->strTelefon) }}"
                        style="width:100%; padding:5px;"
                    >
                </div>
            </div>

            <div class="field">
                <div>FAX:</div>
                <div>
                    <input
                        type="text"
                        name="strTelefax"
                        value="{{ old('strTelefax', $kunde->strTelefax) }}"
                        style="width:100%; padding:5px;"
                    >
                </div>
            </div>

            <div class="field">
                <div>Zu Händen:</div>
                <div>
                    <input
                        type="text"
                        name="strZuHaenden"
                        value="{{ old('strZuHaenden', $kunde->strZuHaenden) }}"
                        style="width:100%; padding:5px;"
                    >
                </div>
            </div>

            <div class="field">
                <div>E-Mail:</div>
                <div>
                    <input
                        type="email"
                        name="strEmail"
                        value="{{ old('strEmail', $kunde->strEmail) }}"
                        style="width:100%; padding:5px;"
                    >
                </div>
            </div>

            <div class="field">
                <div>Angenommen von:</div>
                <div>
                    <input
                        type="text"
                        name="strAngenommenVon"
                        value="{{ old('strAngenommenVon', $kunde->strAngenommenVon) }}"
                        style="width:100%; padding:5px;"
                    >
                </div>
            </div>

            <div class="field">
                <div>Geburtsdatum:</div>
                <div>
                    <input
                        type="date"
                        name="datGeburtsDatum"
                        value="{{ old(
                            'datGeburtsDatum',
                            $kunde->datGeburtsDatum
                                ? \Carbon\Carbon::parse($kunde->datGeburtsDatum)->format('Y-m-d')
                                : ''
                        ) }}"
                        style="width:100%; padding:5px;"
                    >
                </div>
            </div>

            <div class="field">
                <div>Kunde seit:</div>
                <div>
                    <input
                        type="date"
                        name="datKundeSeit"
                        value="{{ old(
                            'datKundeSeit',
                            $kunde->datKundeSeit
                                ? \Carbon\Carbon::parse($kunde->datKundeSeit)->format('Y-m-d')
                                : ''
                        ) }}"
                        style="width:100%; padding:5px;"
                    >
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
                    <input
                        type="checkbox"
                        name="boolLastschrift"
                        value="1"
                        @checked(old('boolLastschrift', $kunde->boolLastschrift))
                    >
                    Ja
                </div>
            </div>

            <div class="field">
                <div>Standard-Zahlungsbedingung:</div>
                <div class="value">
                    {{ $kunde->zahlungsbedingung?->strBezeichnung ?? 'Nicht hinterlegt' }}
                </div>
            </div>

            <div class="field wide">
                <div>Zahlungsunfähigkeit?</div>
                <div class="checkbox">
                    <input
                        type="checkbox"
                        name="boolInsolventOderBeimRechtsanwalt"
                        value="1"
                        @checked(old(
                            'boolInsolventOderBeimRechtsanwalt',
                            $kunde->boolInsolventOderBeimRechtsanwalt
                        ))
                    >

                    Kunde ist insolvent oder beim Rechtsanwalt
                </div>
            </div>

            <div class="field wide">
                <div>Kommentar:</div>
                <div>
                    <input
                        type="text"
                        name="strGrundInsolventOderRA"
                        value="{{ old(
                            'strGrundInsolventOderRA',
                            $kunde->strGrundInsolventOderRA
                        ) }}"
                        style="width:100%; padding:5px;"
                    >
                </div>
            </div>

        </div>

        <div class="section-title">
            Weiterführende Informationen
        </div>

        <div class="grid">

            <div class="field wide">
                <div>Kundenordner im Intranet:</div>
                <div>
                    <input
                        type="text"
                        name="strIntranetFolderPath"
                        value="{{ old(
                            'strIntranetFolderPath',
                            $kunde->strIntranetFolderPath
                        ) }}"
                        style="width:100%; padding:5px;"
                    >
                </div>
            </div>

            <div class="field">
                <div>DATEV-Kundenkonto:</div>
                <div>
                    <input
                        type="text"
                        name="strDatevKundenKonto"
                        value="{{ old(
                            'strDatevKundenKonto',
                            $kunde->strDatevKundenKonto
                        ) }}"
                        style="width:100%; padding:5px;"
                    >
                </div>
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
                    <input
                        type="checkbox"
                        name="rahmenvertragda"
                        value="1"
                        @checked(old('rahmenvertragda', $kunde->rahmenvertragda))
                    >
                </div>
            </div>

            <div class="field">
                <div>WebDNS erlaubt:</div>
                <div class="checkbox">
                    <input
                        type="checkbox"
                        name="bWEBDNSistErlaubt"
                        value="1"
                        @checked(old('bWEBDNSistErlaubt', $kunde->bWEBDNSistErlaubt))
                    >
                </div>
            </div>

            <div class="field">
                <div>Webfreischaltung:</div>
                <div class="checkbox">
                    <input
                        type="checkbox"
                        name="bolwebfreischaltung"
                        value="1"
                        @checked(old('bolwebfreischaltung', $kunde->bolwebfreischaltung))
                    >
                </div>
            </div>

            <div class="field">
                <div>Vertriebsaktivität erforderlich:</div>
                <div class="checkbox">
                    <input
                        type="checkbox"
                        name="boolVertriebsnachfrage"
                        value="1"
                        @checked(old(
                            'boolVertriebsnachfrage',
                            $kunde->boolVertriebsnachfrage
                        ))
                    >
                </div>
            </div>

        </div>

        <div class="info-grid" style="margin-top:12px;">

            <div>
                <strong>Freies Info Feld</strong>

                <textarea
                    name="txtInfo"
                    style="width:100%; min-height:90px; padding:7px;"
                >{{ old('txtInfo', $kunde->txtInfo) }}</textarea>
            </div>

            <div>
                <strong>Serviceinfo</strong>

                <textarea
                    name="txtServiceinfo"
                    style="width:100%; min-height:90px; padding:7px;"
                >{{ old('txtServiceinfo', $kunde->txtServiceinfo) }}</textarea>
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

            <button type="submit" class="button">
                Kunde speichern
            </button>

            <a class="button" href="{{ route('kunden.index') }}">
                Abbrechen
            </a>

            <span class="button disabled">
                Rechnungsanschriften
            </span>

            <span class="button disabled">
                Offene Rechnungen
            </span>

            <span class="button disabled">
                Zahlungsbedingungen
            </span>

        </div>

    </form>

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
