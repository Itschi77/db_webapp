<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8"><style>
@page{margin:25mm 22mm 22mm}body{font-family:DejaVu Sans,sans-serif;font-size:11pt;color:#111}h1{font-size:16pt;margin:28px 0 18px}.sender{font-size:8pt;color:#555;margin-bottom:28px}.right{text-align:right}.amount{font-weight:bold}.box{margin-top:22px;border-top:1px solid #999;padding-top:12px}.preview{position:fixed;top:42%;left:10%;font-size:54pt;color:#ddd;transform:rotate(-30deg);z-index:-1}
</style></head><body>
<div class="preview">VORSCHAU</div>
<div class="right"><strong>{{ $seller['seller_name'] }}</strong><br>{{ $seller['seller_street'] }}<br>{{ $seller['seller_postcode'] }} {{ $seller['seller_city'] }}</div>
<div class="sender">{{ $seller['seller_name'] }} · {{ $seller['seller_street'] }} · {{ $seller['seller_postcode'] }} {{ $seller['seller_city'] }}</div>
<div>{{ $r->addressName ?: $r->strKundenNameAufRechnung }}<br>@if($r->strZuHaenden)z. Hd. {{ $r->strZuHaenden }}<br>@endif{{ $r->strStrasse }}<br>{{ $r->strPLZ }} {{ $r->strOrt }}</div>
<h1>{{ $stage }}. Mahnung zur Rechnung {{ $r->intRechNr }}</h1>
<p>Guten Tag,</p>
<p>zu der Rechnung <strong>{{ $r->intRechNr }}</strong> vom {{ $r->datRechnungsDatum ? date('d.m.Y',strtotime($r->datRechnungsDatum)) : '-' }} besteht weiterhin ein offener Betrag. Das hinterlegte Fälligkeitsdatum war {{ $r->datFaelligkeitsDatum ? date('d.m.Y',strtotime($r->datFaelligkeitsDatum)) : '-' }}.</p>
<div class="box"><table width="100%">
<tr><td>Offener Rechnungsbetrag</td><td class="right">{{ number_format($principal,2,',','.') }} €</td></tr>
@if($fees>0)<tr><td>Mahngebühren gesamt</td><td class="right">{{ number_format($fees,2,',','.') }} €</td></tr>@endif
@if($interest>0)<tr><td>Verzugszinsen</td><td class="right">{{ number_format($interest,2,',','.') }} €</td></tr>@endif
<tr><td><strong>Gesamt offen</strong></td><td class="right amount">{{ number_format($total,2,',','.') }} €</td></tr>
</table></div>
<p>Bitte begleichen Sie den offenen Gesamtbetrag bis zum <strong>{{ $deadline->format('d.m.Y') }}</strong>.</p>
<p>Falls die Zahlung bereits erfolgt ist oder es Rückfragen zur Rechnung gibt, betrachten Sie dieses Schreiben bitte als gegenstandslos und nehmen Sie Kontakt mit uns auf.</p>
<p>Mit freundlichen Grüßen<br>{{ $seller['seller_name'] }}</p>
</body></html>