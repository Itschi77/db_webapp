<div class="notice"><strong>Phase 1: rein lesender Testlauf.</strong> Die Auswahl folgt den bestätigten Filtern des bisherigen Rechnungstools. Es werden noch keine Rechnungen erzeugt und keine Abrechnungsdaten verändert.</div>

<form method="get" action="{{ route('fakturierung.index') }}" class="filters">
    <label>Von <input type="date" name="von" value="{{ $von }}"></label>
    <label>Bis <input type="date" name="bis" value="{{ $bis }}"></label>
    <label>Art
        <select name="art">
            <option value="nachtraeglich" @selected($art === 'nachtraeglich')>Nachträglich abrechnen</option>
            <option value="voraus" @selected($art === 'voraus')>Im Voraus abrechnen</option>
            <option value="domain" @selected($art === 'domain')>Domainaufträge</option>
        </select>
    </label>
    <label>Suche <input type="text" name="q" value="{{ $q }}" placeholder="Auftrag, Kunde, Beschreibung"></label>
    <button type="submit">Auftragsliste laden</button>
</form>

<div class="meta">Angemeldet als: <strong>{{ $adUsername ?: 'unbekannt' }}</strong> · {{ $auftraege->count() }} passende Aufträge (max. 500)</div>

<div class="table-wrap"><table>
    <thead><tr><th>Letzte Rechnung</th><th>Auftrag</th><th>Kunde</th><th>Beschreibung</th><th>Fakt. ab</th><th>Storno ab</th><th>E-Mail</th><th></th></tr></thead>
    <tbody>
    @forelse($auftraege as $a)
        <tr @class(['selected-row' => $selected && (int)$selected->intAufNr === (int)$a->intAufNr])>
            <td>{{ $a->letztesRechnungsdatum ? \Carbon\Carbon::parse($a->letztesRechnungsdatum)->format('d.m.Y') : '–' }}</td>
            <td>{{ $a->intAufNr }}</td>
            <td>{{ $kunden->get($a->intKID)?->strName ?? ('Kunde '.$a->intKID) }} <span class="muted">({{ $a->intKID }})</span></td>
            <td>{{ $a->strBeschreibung }}</td>
            <td>{{ $a->datFakturierAb ? \Carbon\Carbon::parse($a->datFakturierAb)->format('d.m.Y') : '–' }}</td>
            <td>{{ $a->datStorniereAb ? \Carbon\Carbon::parse($a->datStorniereAb)->format('d.m.Y') : '–' }}</td>
            <td>{{ $a->boolEmailRechnung ? ($a->rechnungEmail ?: 'Ja, Adresse fehlt') : 'Nein' }}</td>
            <td><a href="{{ route('fakturierung.index', array_filter(['von'=>$von,'bis'=>$bis,'art'=>$art,'q'=>$q,'auftrag'=>$a->intAufNr])) }}">Vorschau</a></td>
        </tr>
    @empty
        <tr><td colspan="8">Für diesen Zeitraum und Filter wurden keine Aufträge gefunden.</td></tr>
    @endforelse
    </tbody>
</table></div>

@if($selected)
<section class="preview-box">
    <h2>Auftrag {{ $selected->intAufNr }} · {{ $kunden->get($selected->intKID)?->strName ?? ('Kunde '.$selected->intKID) }}</h2>
    <p><strong>{{ $selected->strBeschreibung }}</strong></p>
    @if($selected->strAbrechnungshinweis)<p><strong>Abrechnungshinweis:</strong> {{ $selected->strAbrechnungshinweis }}</p>@endif
    @if($selected->boolEingefroren)<p class="warning"><strong>Achtung:</strong> Auftrag ist als eingefroren markiert. Das alte Auftragslistenfenster filtert diesen Status nicht aus; die eigentliche Berechnungslogik wird separat nachgebildet.</p>@endif
    <h3>Auftragspositionen · Rohdaten für den Testlauf</h3>
    <div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Beschreibung</th><th>Menge</th><th>Preis netto</th><th>Rabatt</th><th>USt.</th><th>Fakt. ab</th><th>Fakt. bis</th><th>Abr.-Art</th><th>Staffel</th><th>Bisher berechnet</th></tr></thead>
        <tbody>
        @forelse($positionen as $p)
            @php($b = $berechnet->get($p->intID))
            <tr>
                <td>{{ $p->intID }}</td><td>{{ $p->strBeschreibung }}</td>
                <td>{{ number_format((float)$p->intMenge, 2, ',', '.') }}</td>
                <td>{{ number_format((float)$p->fEndpreis, 2, ',', '.') }} €</td>
                <td>{{ number_format((float)$p->fRabattInProzent, 2, ',', '.') }} %</td>
                <td>{{ number_format((float)$p->intMwstsatz, 2, ',', '.') }} %</td>
                <td>{{ $p->datFakturierAb ? \Carbon\Carbon::parse($p->datFakturierAb)->format('d.m.Y') : '–' }}</td>
                <td>{{ $p->datFakturierBis ? \Carbon\Carbon::parse($p->datFakturierBis)->format('d.m.Y') : '–' }}</td>
                <td>{{ $p->intAbrechnungsArt ?? '–' }}</td>
                <td>{{ $p->intStaffelTyp ?? '–' }} / {{ $p->intStaffelgruppe ?? '–' }}</td>
                <td>{{ $b ? $b->anzahl.'×, zuletzt '.\Carbon\Carbon::parse($b->zuletztBerechnet)->format('d.m.Y') : 'noch nie' }}</td>
            </tr>
        @empty
            <tr><td colspan="11">Keine Auftragspositionen vorhanden.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <p class="muted">Noch nicht enthalten: Intervallberechnung, Accountingmengen, Staffelpreise, Domainpreise, Vorberechnung und endgültige Rechnungs-/Steuersummen. Diese Logik wird im nächsten Schritt einzeln gegen das Alttool abgeglichen.</p>
</section>
@endif
