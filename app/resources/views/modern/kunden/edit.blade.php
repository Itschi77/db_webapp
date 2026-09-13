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
	.button {
    display: inline-block;
    padding: 10px 16px;
    border: 0;
    border-radius: 8px;
    background: #0069c2;
    color: white;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
}

.button:hover {
    opacity: 0.9;
}

input[type="text"],
input[type="email"],
input[type="date"] {
    width: 100%;
    padding: 9px 10px;
    border: 1px solid #ccd2d8;
    border-radius: 6px;
    font-size: 14px;
    background: white;
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

<form method="POST" action="{{ route('kunden.update', $kunde->intID) }}">
    @csrf
    @method('PUT')

<body>

<div class="page">

    <div class="topbar">
        <div style="display:flex; gap:18px; align-items:center;">
            <a href="{{ route('kunden.index') }}">← Kunden</a>
            <a href="{{ route('dashboard') }}">Hauptmenü</a>
        </div>

        <a href="{{ route('frontend.switch', 'classic') }}">
            ← Klassische Ansicht
        </a>
    </div>

    <form method="POST" action="{{ route('kunden.update', $kunde->intID) }}">
        @csrf
        @method('PUT')

        <div class="hero">
            <div>
                <div class="eyebrow">Kunde #{{ $kunde->intID }}</div>

                <input
                    type="text"
                    name="strName"
                    value="{{ old('strName', $kunde->strName) }}"
                    required
                    style="
                        font-size:30px;
                        font-weight:700;
                        width:100%;
                        border:1px solid #ccd2d8;
                        border-radius:8px;
                        padding:8px 10px;
                    "
                >
            </div>
        </div>

        <div class="grid">

            <div class="card">
                <h2>Stammdaten</h2>

                <div class="field">
                    <div class="label">Firma</div>
                    <div>
                        <input
                            type="checkbox"
                            name="boolIstFirma"
                            value="1"
                            @checked(old('boolIstFirma', $kunde->boolIstFirma))
                        >
                    </div>
                </div>

                <div class="field">
                    <div class="label">Anrede</div>
                    <div>
                        <input
                            type="text"
                            name="strAnrede"
                            value="{{ old('strAnrede', $kunde->strAnrede) }}"
                        >
                    </div>
                </div>

                <div class="field">
                    <div class="label">Zu Händen</div>
                    <div>
                        <input
                            type="text"
                            name="strZuHaenden"
                            value="{{ old('strZuHaenden', $kunde->strZuHaenden) }}"
                        >
                    </div>
                </div>

                <div class="field">
                    <div class="label">Straße</div>
                    <div>
                        <input
                            type="text"
                            name="strStrasse"
                            value="{{ old('strStrasse', $kunde->strStrasse) }}"
                        >
                    </div>
                </div>

                <div class="field">
                    <div class="label">PLZ</div>
                    <div>
                        <input
                            type="text"
                            name="strPLZ"
                            value="{{ old('strPLZ', $kunde->strPLZ) }}"
                        >
                    </div>
                </div>

                <div class="field">
                    <div class="label">Ort</div>
                    <div>
                        <input
                            type="text"
                            name="strOrt"
                            value="{{ old('strOrt', $kunde->strOrt) }}"
                        >
                    </div>
                </div>

                <div class="field">
                    <div class="label">Telefon</div>
                    <div>
                        <input
                            type="text"
                            name="strTelefon"
                            value="{{ old('strTelefon', $kunde->strTelefon) }}"
                        >
                    </div>
                </div>

                <div class="field">
                    <div class="label">Fax</div>
                    <div>
                        <input
                            type="text"
                            name="strTelefax"
                            value="{{ old('strTelefax', $kunde->strTelefax) }}"
                        >
                    </div>
                </div>

                <div class="field">
                    <div class="label">E-Mail</div>
                    <div>
                        <input
                            type="email"
                            name="strEmail"
                            value="{{ old('strEmail', $kunde->strEmail) }}"
                        >
                    </div>
                </div>

                <div class="field">
                    <div class="label">Angenommen von</div>
                    <div>
                        <input
                            type="text"
                            name="strAngenommenVon"
                            value="{{ old('strAngenommenVon', $kunde->strAngenommenVon) }}"
                        >
                    </div>
                </div>

                <div class="field">
                    <div class="label">Geburtsdatum</div>
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
                        >
                    </div>
                </div>

                <div class="field">
                    <div class="label">Kunde seit</div>
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
                        >
                    </div>
                </div>

            </div>

            <div class="card">
                <h2>Status & Zahlungsart</h2>

                <div class="field">
                    <div class="label">Aktiver Kunde</div>
                    <div>
                        <input
                            type="checkbox"
                            name="boolAktiverKunde"
                            value="1"
                            @checked(old('boolAktiverKunde', $kunde->boolAktiverKunde))
                        >
                    </div>
                </div>

                <div class="field">
                    <div class="label">Lastschrift</div>
                    <div>
                        <input
                            type="checkbox"
                            name="boolLastschrift"
                            value="1"
                            @checked(old('boolLastschrift', $kunde->boolLastschrift))
                        >
                    </div>
                </div>

                <div class="field">
                    <div class="label">Zahlungsbedingung</div>
                    <div class="readonly">
                        {{ $kunde->zahlungsbedingung?->strBezeichnung ?? 'Nicht hinterlegt' }}
                    </div>
                </div>

                <div class="field">
                    <div class="label">Zahlungsunfähigkeit</div>
                    <div>
                        <input
                            type="checkbox"
                            name="boolInsolventOderBeimRechtsanwalt"
                            value="1"
                            @checked(old(
                                'boolInsolventOderBeimRechtsanwalt',
                                $kunde->boolInsolventOderBeimRechtsanwalt
                            ))
                        >
                    </div>
                </div>

                <div class="field">
                    <div class="label">Kommentar</div>
                    <div>
                        <input
                            type="text"
                            name="strGrundInsolventOderRA"
                            value="{{ old(
                                'strGrundInsolventOderRA',
                                $kunde->strGrundInsolventOderRA
                            ) }}"
                        >
                    </div>
                </div>

                <div class="field">
                    <div class="label">Rahmenvertrag</div>
                    <div>
                        <input
                            type="checkbox"
                            name="rahmenvertragda"
                            value="1"
                            @checked(old('rahmenvertragda', $kunde->rahmenvertragda))
                        >
                    </div>
                </div>

                <div class="field">
                    <div class="label">WebDNS erlaubt</div>
                    <div>
                        <input
                            type="checkbox"
                            name="bWEBDNSistErlaubt"
                            value="1"
                            @checked(old('bWEBDNSistErlaubt', $kunde->bWEBDNSistErlaubt))
                        >
                    </div>
                </div>

                <div class="field">
                    <div class="label">Webfreischaltung</div>
                    <div>
                        <input
                            type="checkbox"
                            name="bolwebfreischaltung"
                            value="1"
                            @checked(old('bolwebfreischaltung', $kunde->bolwebfreischaltung))
                        >
                    </div>
                </div>

                <div class="field">
                    <div class="label">Vertriebsaktivität erforderlich</div>
                    <div>
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

        </div>

        <div class="card" style="margin-top:20px;">
            <h2>Weiterführende Informationen</h2>

            <div class="field">
                <div class="label">Kundenordner im Intranet</div>
                <div>
                    <input
                        type="text"
                        name="strIntranetFolderPath"
                        value="{{ old(
                            'strIntranetFolderPath',
                            $kunde->strIntranetFolderPath
                        ) }}"
                    >
                </div>
            </div>

            <div class="field">
                <div class="label">DATEV-Kundenkonto</div>
                <div>
                    <input
                        type="text"
                        name="strDatevKundenKonto"
                        value="{{ old(
                            'strDatevKundenKonto',
                            $kunde->strDatevKundenKonto
                        ) }}"
                    >
                </div>
            </div>

            <div class="field">
                <div class="label">Offene Mahngebühren</div>
                <div class="readonly">
                    {{ number_format((float) $kunde->{'fOffeneMahngebühren'}, 2, ',', '.') }} €
                </div>
            </div>

            <div style="margin-top:18px;">
                <div class="label">Freies Info Feld</div>

                <textarea
                    name="txtInfo"
                    rows="5"
                >{{ old('txtInfo', $kunde->txtInfo) }}</textarea>
            </div>

            <div style="margin-top:18px;">
                <div class="label">Serviceinfo</div>

                <textarea
                    name="txtServiceinfo"
                    rows="5"
                >{{ old('txtServiceinfo', $kunde->txtServiceinfo) }}</textarea>
            </div>
        </div>

        <div class="actions">

            <button type="submit" class="primary-button">
                Änderungen speichern
            </button>

            <a
                href="{{ route('kunden.show', $kunde->intID) }}"
                class="secondary-button"
            >
                Abbrechen
            </a>

        </div>

    </form>

</div>

</body>
</html>
