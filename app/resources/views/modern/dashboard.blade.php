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

        <a
            class="switch-link"
            href="{{ route('frontend.switch', 'classic') }}"
        >
            ← Zur klassischen Ansicht
        </a>
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

            <div class="disabled">
                Rechnungen
            </div>

            <div class="disabled">
                Auftragsverwaltung
            </div>

            <div class="disabled">
                DATEV und Bilanzen
            </div>
        </div>

        <div class="card">
            <h2>Domains</h2>

            <div class="disabled">
                Kundenzuordnung
            </div>

            <div class="disabled">
                Domain-Aufträge
            </div>

            <div class="disabled">
                Domain-Einträge bearbeiten
            </div>
        </div>

        <div class="card">
            <h2>Vertrieb</h2>

            <div class="disabled">
                Branchen
            </div>

            <div class="disabled">
                Branchen exportieren
            </div>
        </div>

        <div class="card">
            <h2>Administration</h2>

            <div class="disabled">
                Zahlungsarten
            </div>

            <div class="disabled">
                Produkte
            </div>

            <div class="disabled">
                SMS-Zugänge
            </div>
        </div>

        <div class="card">
            <h2>Technik</h2>

            <div class="disabled">
                Verbindungen
            </div>

            <div class="disabled">
                Netze
            </div>

            <div class="disabled">
                Ports
            </div>
        </div>

    </div>

</div>

</body>
</html>
