<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>{{ ($isPreview ?? true) ? 'Rechnungsvorschau' : 'Rechnung' }} {{ $invoiceNumber ?? $order->intAufNr }}</title>
<style>
@page { margin: 13mm 15mm 24mm 18mm; }
* { box-sizing: border-box; }
body { margin:0; color:#222; font-family: DejaVu Sans, Arial, sans-serif; font-size:9.2pt; line-height:1.35; }
.preview { position:fixed; top:8mm; left:62mm; z-index:-1; color:#e9e9e9; font-size:38pt; font-weight:bold; transform:rotate(-18deg); }
.header { width:100%; margin-bottom:15mm; }
.logo { font-size:33pt; line-height:1; font-weight:bold; letter-spacing:-2px; color:#174d8a; }
.logo span { color:#d9282f; }
.company { text-align:right; font-size:8pt; color:#444; line-height:1.45; }
.sender { font-size:6.8pt; color:#666; text-decoration:underline; margin-bottom:3mm; }
.address { min-height:34mm; width:92mm; font-size:10.5pt; }
.address .attention { margin-bottom:2mm; }
.place-date { text-align:right; margin-top:-7mm; margin-bottom:10mm; }
h1 { color:#184f8b; font-size:17pt; margin:0 0 8mm; font-weight:normal; }
.notice { border:1px solid #d9282f; color:#a51e24; padding:2.5mm; margin-bottom:5mm; font-weight:bold; text-align:center; }
table.positions { width:100%; border-collapse:collapse; font-size:8.2pt; }
.positions th { color:#184f8b; font-weight:bold; text-align:left; border-bottom:1px solid #184f8b; padding:1.7mm 1mm; }
.positions td { vertical-align:top; border-bottom:1px solid #d8d8d8; padding:2mm 1mm; }
.positions .num { text-align:right; white-space:nowrap; }
.kind { color:#666; font-size:7.2pt; }
.totals { width:73mm; margin:5mm 0 6mm auto; border-collapse:collapse; }
.totals td { padding:1mm; }
.totals td:last-child { text-align:right; white-space:nowrap; }
.totals .gross td { border-top:1px solid #184f8b; color:#184f8b; font-weight:bold; font-size:10pt; padding-top:2mm; }
.payment { margin:5mm 0 4mm; }
.meta { width:100%; margin-top:8mm; border-top:1px solid #aaa; border-collapse:collapse; font-size:7.5pt; }
.meta td { padding:2mm 1mm 0; vertical-align:top; width:25%; }
.meta strong { display:block; color:#555; font-weight:normal; }
.footer { position:fixed; left:18mm; right:15mm; bottom:7mm; border-top:1px solid #184f8b; padding-top:2mm; font-size:6.2pt; color:#555; }
.footer table { width:100%; border-collapse:collapse; }
.footer td { vertical-align:top; width:33.333%; padding-right:3mm; }
.error-list { color:#9f2d20; margin:2mm 0 0 5mm; padding:0; }
</style>
</head>
<body>
@if($isPreview ?? true)<div class="preview">VORSCHAU</div>@endif
<table class="header"><tr>
<td><div class="logo">tops<span>.</span>net</div></td>
<td class="company"><strong>tops.net GmbH &amp; Co. KG</strong><br>Holtorfer Straße 35<br>D-53229 Bonn<br>Telefon +49 (0)228 9771 0<br>info@tops.net · www.tops.net</td>
</tr></table>

<div class="sender">tops.net GmbH &amp; Co. KG · Holtorfer Straße 35 · D-53229 Bonn</div>
<div class="address">
@if($testRun['address'])
    <strong>{{ $testRun['address']->strName }}</strong><br>
    @if($testRun['address']->strZuHaenden)<div class="attention">{{ $testRun['address']->strZuHaenden }}</div>@endif
    {{ $testRun['address']->strStrasse }}<br>
    {{ $testRun['address']->strPLZ }} {{ $testRun['address']->strOrt }}
@else
    <strong>Rechnungsanschrift fehlt</strong>
@endif
</div>
<div class="place-date">Bonn, {{ $testRun['invoiceDate']->format('d.m.Y') }}</div>

<h1>{{ ($isPreview ?? true) ? 'Rechnungsvorschau' : 'Rechnung' }} {{ $invoiceNumber ?? $numberSimulation['next'] }}</h1>
@if($isPreview ?? true)<div class="notice">ENTWURF – KEINE RECHNUNG · Nummer nur simuliert, nicht reserviert oder vergeben</div>@endif
@if($testRun['issues']->isNotEmpty())
<div><strong>Blockierende Prüfpunkte:</strong><ul class="error-list">@foreach($testRun['issues'] as $issue)<li>{{ $issue }}</li>@endforeach</ul></div>
@endif
<table class="positions">
<thead><tr><th style="width:8%">Pos.</th><th style="width:9%">MwSt.</th><th>Beschreibung</th><th style="width:17%">Genutzt / Abzurechnen</th><th style="width:15%;text-align:right">Preis</th></tr></thead>
<tbody>
@forelse($testRun['documentRows'] as $i => $line)
<tr>
<td>{{ $i + 1 }}</td>
<td>{{ number_format($line->taxRate, 2, ',', '.') }} %</td>
<td><span class="kind">{{ $line->kind }} · Pos. #{{ $line->positionId }}</span><br>{{ $line->description }}</td>
<td>{{ $line->quantityLabel ?: '1' }}</td>
<td class="num">{{ number_format($line->net, 2, ',', '.') }} €</td>
</tr>
@empty
<tr><td colspan="5">Für den gewählten Zeitraum sind keine neuen Rechnungspositionen vorhanden.</td></tr>
@endforelse
</tbody>
</table>

<table class="totals">
<tr><td>Summe netto</td><td>{{ number_format($testRun['net'], 2, ',', '.') }} €</td></tr>
@foreach($testRun['taxByRate'] as $rate => $amount)
<tr><td>zzgl. {{ number_format((float) $rate, 2, ',', '.') }} % MwSt.</td><td>{{ number_format($amount, 2, ',', '.') }} €</td></tr>
@endforeach
<tr class="gross"><td>Rechnungsbetrag</td><td>{{ number_format($testRun['gross'], 2, ',', '.') }} €</td></tr>
</table>
<div class="payment">{{ $testRun['paymentText'] ?: ($testRun['payment']->strBezeichnung ?? '') }}</div>
@if($testRun['skonto']->isNotEmpty())
<div>Skonto: @foreach($testRun['skonto'] as $s){{ number_format($s['percent'], 2, ',', '.') }} % bis {{ $s['date']->format('d.m.Y') }} ({{ number_format($s['gross'], 2, ',', '.') }} €)@if(!$loop->last) · @endif @endforeach</div>
@endif
@if($testRun['order']?->strAbrechnungshinweis)
<div style="margin-top:3mm">{{ $testRun['order']->strAbrechnungshinweis }}</div>
@endif
<div style="margin-top:4mm;font-size:8pt;color:#555">Leistungszeitraum {{ $from->format('d.m.Y') }} bis {{ $to->format('d.m.Y') }}.</div>

<table class="meta"><tr>
<td><strong>Kundennummer</strong>00-{{ str_pad((string) $order->intKID, 6, '0', STR_PAD_LEFT) }}</td>
<td><strong>Auftragsnummer</strong>{{ $order->intAufNr }}</td>
<td><strong>Buchungskonto</strong>{{ $testRun['customer']->strDatevKundenKonto ?? '–' }}</td>
<td><strong>Rechnung an</strong>{{ $testRun['address']->strEmail ?? 'Papier' }}</td>
</tr></table>

<div class="footer"><table><tr>
<td><strong>tops.net GmbH &amp; Co. KG</strong><br>Holtorfer Straße 35 · 53229 Bonn<br>HRA 4251, Amtsgericht Bonn</td>
<td>Sparkasse KölnBonn<br>IBAN DE88 3705 0198 0032 9006 49<br>BIC COLSDE33XXX</td>
<td>Volksbank Köln Bonn eG<br>IBAN DE63 3806 0186 0102 5010 12<br>BIC GENODED1BRS</td>
</tr></table></div>
</body>
</html>
