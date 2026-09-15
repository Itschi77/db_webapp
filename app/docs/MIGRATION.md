# Technische Dokumentation – DB-Webmigration

Stand: 15.09.2026

## 1. Ziel und Umfang

Die bestehende Microsoft-Access-Anwendung wird schrittweise durch eine Laravel-Webanwendung ersetzt. Die fachliche Logik des Altsystems wird soweit sinnvoll erhalten, gleichzeitig werden Datenbankzugriffe, Rechte und Benutzeroberfläche sauber getrennt.

Nicht Bestandteil dieser Anwendung ist die Arbeitszeit-/Auswertungsfunktion; dafür existiert ein separates Werkzeug.

## 2. Zielarchitektur

- Server: Debian 13 auf `janus`
- Anwendung: Laravel 13 / PHP 8.4
- Container: Docker Compose
- Reverse Proxy: Caddy
- Ziel-Datenbank der Webanwendung: PostgreSQL
- Übergangs-/Quelldatenbanken: Microsoft SQL Server 2019 auf `CARDEA`
- Weboberflächen: Classic und Modern
- Mehrfenster-Arbeitsbereich im Browser für paralleles Öffnen und Vergleichen von Datensätzen

## 3. SQL-Server-Datenbanken

### `topsnetdb_safe`
Enthält insbesondere Kundenstammdaten und kundenbezogene Stammtabellen.

Wichtige Tabellen:
- `dbo.tblKunde`
- `dbo.tblAnsprechpartner`
- `dbo.tblKundenBranchen`
- `dbo.tblBranchen`
- `dbo.tblZahlungsbedingung`

### `accountings`
Enthält Buchhaltungs-, Auftrags- und Accounting-Daten.

Wichtige Tabellen:
- `dbo.tblRechnungsanschrift`
- `dbo.tblBankverbindung`
- `dbo.tblRechnung`
- `dbo.tblAuftrag`
- `dbo.tblAuftragPos`
- `dbo.tblProdukt`
- `dbo.tblAnbindungen`
- `dbo.tblAnbindungNetze`
- `dbo.tblAnbindungDialin`
- `dbo.tblPort`
- `dbo.tblDomains` als historischer/Accounting-bezogener Domainbestand

### `domains`
Die eigentliche Domainverwaltung liegt in einer eigenen SQL-Server-Datenbank. Diese ist fachlich von `accountings.dbo.tblDomains` zu unterscheiden und wird bei der späteren Domainmigration separat betrachtet.

## 4. Zentrale Datenbeziehungen

- Kunde: `topsnetdb_safe.dbo.tblKunde.intID`
- Auftrag: `accountings.dbo.tblAuftrag.intKID -> tblKunde.intID`
- Auftragsposition: `accountings.dbo.tblAuftragPos.intAufNr -> tblAuftrag.intAufNr`
- Rechnungsanschrift: `accountings.dbo.tblRechnungsanschrift.intKID -> tblKunde.intID`
- Ansprechpartner: `topsnetdb_safe.dbo.tblAnsprechpartner.intKID -> tblKunde.intID`
- Kundenbranche: `topsnetdb_safe.dbo.tblKundenBranchen` verbindet Kunden und Branchen
- Anbindung: `accountings.dbo.tblAnbindungen.intAuftragsPos -> tblAuftragPos.intID`

## 5. Anbindungstypen

| Typ | Bedeutung | Referenztabelle |
| --- | --- | --- |
| 1 | Netz-Accounting | `tblAnbindungNetze` |
| 2 | Port-Accounting | `tblPort` |
| 3 | Dialin-Accounting | `tblAnbindungDialin` |
| 4 | Fremdaccounting | `tblAnbindungAuswertung.intAnbindungID -> tblAnbindungen.intID` |
| 5 | Dialin-Zeitabrechnung | `tblAnbindungDialin` |
| 6 | Domain-Accounting | `accountings.dbo.tblDomains` |
| 7 | SMS-Service | Altbestand, Referenz noch nicht vollständig migriert |

Neue Anbindungen werden nur für die ausreichend verstandenen Typen 1, 2, 3, 5 und 6 angeboten. Typ 7 (SMS) ist strikt lesender Altbestand und kann weder neu angelegt noch geändert werden. Fremdaccounting (Typ 4) wird derzeit als lesende Monatsauswertung dargestellt; das Anlegen neuer Typ-4-Anbindungen bleibt gesperrt.

## 6. Aktuell umgesetzte Funktionen

- Kunden suchen, anzeigen, anlegen und bearbeiten
- Branchen-Zuordnung
- Ansprechpartner anlegen und bearbeiten
- Rechnungsanschriften anlegen und bearbeiten
- offene Rechnungen anzeigen und einzelne Rechnungen aus der Kundenansicht öffnen
- Aufträge suchen, anzeigen, anlegen und bearbeiten
- Aufträge-Wiedervorlage entsprechend der Access-Logik: Auftrag wird gelistet, wenn mindestens eine Position `tblAuftragPos.txtInfo = "WV"` hat
- Auftragspositionen anlegen und bearbeiten
- Produktdaten bei Auftragspositionen übernehmen
- Ticket-Mail aus einem Auftrag vorbereiten
- Anbindungen je Auftragsposition anzeigen, anlegen und bearbeiten
- Referenzsuche für Netz, Port, Dialin und Domain über verständliche Vorschlagslisten statt reiner ID-Eingabe
- SMS-Anbindungen (Typ 7) ausschließlich als nicht editierbaren Altbestand anzeigen
- zentrale Aufträge-Wiedervorlage entsprechend Access: Aufträge werden über `tblAuftragPos.txtInfo = "WV"` markiert und dedupliziert
- globale Rechnungsverwaltung mit Suche, Offen/Bezahlt-Filter und Rechnungsdetail (lesend)
- Rechnungen ohne USt. ab frei wählbarem Rechnungsdatum entsprechend `qRechnungenOhneSteuernAb` (lesend)
- Lastschriften-Vorschau und Sammelmarkierung als bezahlt nach bestätigter Access-Logik
- Classic-/Modern-Frontend umschaltbar
- Access-artiger Mehrfenster-Arbeitsbereich im Browser
- Branchenübersicht mit Kunden, Adresse und Telefon sowie XLSX-Export der Branchenzuordnungen

## 7. Wiedervorlage und Rechnungen

Die Aufträge-Wiedervorlage bildet die Access-Datensatzquelle fachlich nach: `tblAuftrag` wird mit `tblAuftragPos` verknüpft und nur Aufträge mit `tblAuftragPos.txtInfo = "WV"` werden gelistet. Mehrere passende Positionen desselben Auftrags führen in der Webanwendung nur zu einem Listeneintrag. `datWiedervorlageVertrieb` ist dabei ein Positionsfeld, aber nicht das Auswahlkriterium der zentralen WV-Liste.

Die globale Rechnungsverwaltung liest `accountings.dbo.tblRechnung` und verknüpft über `tblRechnung.intAufNr -> tblAuftrag.intAufNr -> tblKunde.intID`. Sie ist derzeit bewusst read-only. Das Rechnungsdetail bildet die bestätigten Felder der Access-Maske ab, darunter Rechnungs-/Versand-/Fälligkeits-/Bezahldaten, Zahlungsbedingung, Beträge, Ratenzahlung, Gutschrift, Verzugszinsen, Skonto, Mahnstufen, Mahngebühren, Kundensperrung, Verlustabschreibung und strittige Rechnungen. Der UNC-Rechnungspfad wird angezeigt und kann kopiert werden; erreichbare Rechnungsdateien werden über die kontrollierte, read-only Serverfreigabe geöffnet.

## 8. Rechteprinzip

Die Migration folgt dem Least-Privilege-Prinzip. Schreibrechte werden nur gezielt für die tatsächlich benötigten Tabellen vergeben. DELETE bleibt grundsätzlich gesperrt, sofern es nicht fachlich ausdrücklich benötigt und entschieden wurde.

Beispiele:
- `tblAuftrag`: SELECT, INSERT, UPDATE
- `tblAuftragPos`: SELECT, INSERT, UPDATE
- `tblAnbindungen`: SELECT, INSERT, UPDATE
- Referenztabellen wie `tblProdukt`, `tblPort`, `tblAnbindungNetze`, `tblAnbindungDialin`, `tblDomains`: derzeit nur lesend

Zugangsdaten, Kennwörter und andere Secrets gehören nicht in diese Dokumentation und nicht ins Repository.

## 9. Besondere Sicherheitsregeln

- Dialin-Kennwörter werden in der Webanwendung nicht angezeigt.
- SQL-Zugangsdaten liegen nur in der lokalen `.env` und werden nicht versioniert.
- Es werden keine künstlichen Testdatensätze in produktionsnahen Tabellen angelegt.
- Historische Platzhalterwerte werden dokumentiert und nicht stillschweigend umgedeutet.
- Die Access-WV-Abfrage verwendete `SELECT DISTINCT tblAuftrag.*, tblKunde.*, tblAuftragPos.txtInfo` und scheiterte wegen des OLE-Feldes in `tblAuftrag`; die Webversion bildet dieselbe fachliche Auswahl ohne OLE/DISTINCT nach.

### 9.1 Rechnungsdateien aus der SMB-Freigabe

Die in `tblRechnung.strPfadZurRechnung` gespeicherten UNC-Pfade zeigen auf `\\midas\bh`. Auf `janus` wird diese Freigabe read-only nach `/mnt/midas-bh` eingebunden und ebenfalls read-only in den Laravel-Container durchgereicht. Die Webanwendung übersetzt ausschließlich Pfade unterhalb von `\\midas\bh` und liefert die gefundene Datei über eine kontrollierte Rechnungsroute aus. Freie Dateipfade aus Benutzereingaben werden nicht akzeptiert.

Für die CIFS-Einbindung wird auf Debian `cifs-utils` verwendet. Zugangsdaten liegen ausschließlich in einer geschützten Credentials-Datei, z. B. `/root/.smb-midas` mit Modus `600`. Benutzername, Passwort oder Domain gehören weder in Git noch in diese Dokumentation. Der Mount ist read-only. Beispiel ohne Zugangsdaten:

```text
//midas/bh  /mnt/midas-bh  cifs  credentials=/root/.smb-midas,ro,vers=3.0,iocharset=utf8  0  0
```

Beispiel für die Pfadabbildung: `\\midas\bh\Rechnungswesen\2024\Rechnungen\Papier\2024000043.doc` wird serverseitig zu `/mnt/midas-bh/Rechnungswesen/2024/Rechnungen/Papier/2024000043.doc`.

### 9.2 Fremdaccounting

Das Access-Formular `frmFremdAuswertung` basiert auf `AbfrageFremdAccTest`. Diese verknüpft `tblAnbindungen.intID` mit `tblAnbindungAuswertung.intAnbindungID`, filtert auf `tblAnbindungen.intTyp = 4` und verlangt Monat sowie Jahr als Parameter. Die Werte `decMBin`, `decMBout`, `decGesamt` und `strrechnungsinfo` werden direkt aus `tblAnbindungAuswertung` gelesen; `decGesamt` wird in dieser Abfrage nicht berechnet.

Die Webanwendung stellt diese Auswertung read-only unter `/fremdaccounting` bereit. Monat und Jahr werden explizit ausgewählt. Zusätzlich werden, soweit vorhanden, Kunde, Auftrag und Auftragsposition verlinkt. Der SQL-Benutzer `janus_connect` benötigt dafür ausschließlich `SELECT` auf `dbo.tblAnbindungAuswertung`.

- Der Access-artige Mehrfenster-Manager fängt Links zu Kunden, Aufträgen, Rechnungen, Wiedervorlagen, Dokumentation und Fremdaccounting ab. Eine fehlerhafte Pfad-Erweiterung beim Fremdaccounting hatte den JavaScript-Manager vollständig deaktiviert; die Pfadprüfung wurde korrigiert und Fremdaccounting sauber ergänzt.

### 9.3 DATEV / Rechnungs-Kontierung
Das Access-Menü `frmDatevBilanzen` öffnet für die beiden Rechnungsberichte die Reports `ZeigeRechnungenDatevInfos` und `ZeigeRechnungenDatevInfosKurz`. Beide verwenden dieselbe Datenlogik: `tblRechnung` wird über `tblAuftragPosBerechnet.intRechnungIntID` mit den tatsächlich berechneten Auftragspositionen verbunden; `tblAuftragPos` liefert `fRabattInProzent`, `tblDatevBezeichnungen` die Produktkontierung und `tblKunde.strDatevKundenKonto` das Kundenkonto. Netto und Steuer werden wie in Access nach Rabatt berechnet.

Die Webanwendung stellt unter `/datev` die sechs bekannten Access-Auswertungen bereit: Rechnungen ausführlich/kurz, Produkte, Kunden, Kunden ohne DATEV-Konto und alle Kundenkonten. Der Zeitraum wird explizit mit Von-/Bis-Datum gewählt; die Ansicht ist read-only. Fehlende Kunden- oder Produktkontierungen werden wie in Access deutlich markiert. Für `tblAuftragPosBerechnet` und `tblDatevBezeichnungen` besitzt `janus_connect` ausschließlich SELECT-Rechte. Die Produkt- und Kundenübersichten verwenden dieselbe Rechnungs-/Positionsbasis wie die Rechnungsberichte und gruppieren nach Produktkonto bzw. Kundenkonto. `ZeigeKundenOhneDatevKonten` bildet die Access-Bedingung nach: aktive bzw. nach dem 31.12.2000 stornierte Papieraufträge und fehlendes `strDatevKundenKonto`. `Alle Kundenkonten` zeigt Kunden mit nichtleerem `strDatevKundenKonto`. Der historische Access-Kommentar, dass Vor-/Nachberechnung in den Berichten problematisch sein kann, bleibt als Migrationshinweis bestehen und wird nicht stillschweigend als fachlich korrekt angenommen.


### 9.4 Rechnungslauf-Export

Das Access-Hauptmenü startet über `Befehl49_Click` das Makro `Rechnungslauf Exportieren (xlsx)`. Das Makro exportiert ausschließlich die Abfrage `Rechnungslauf Exportieren`. Die Abfrage verknüpft `tblRechnung`, `tblAuftrag` und `tblZahlungsbedingung`, filtert auf einen vom Benutzer gewählten Rechnungszeitraum und liefert Rechnungsdatum, Rechnungsnummer, Kundenname, Auftragsbeschreibung, Betrag, Fälligkeit, Zahlungsart, Lastschrifteinzug, Bezahldatum, Zahlbetrag, Kommentar, Rechnungspfad und Forderungsausfall-Datum.

Die Access-Logik für den Betrag wird beibehalten: Bei Lastschrift (`tblZahlungsbedingung.boolIstBankeinzug = 1`) wird `fRechnungsbetragMitSkonto1` verwendet, sofern dieser Wert größer als 0 ist; andernfalls `fRechnungsbetrag`. Zahlungsart wird als `LS` bzw. `R` ausgegeben. Die Webanwendung stellt den Export unter `/rechnungslauf` bereit. Anders als das historische Access-Makro, das technisch ein Excel-97-Format ausgibt, erzeugt die Webanwendung eine echte `.xlsx`-Datei. Der Export ist rein lesend. `janus_connect` benötigt dafür zusätzlich ausschließlich `SELECT` auf `accountings.dbo.tblZahlungsbedingung`.


### 9.5 Lastschriften als bezahlt markieren

Das Access-Formular `frmLastschriftenBezahlen` verwendet die Abfrage `AAAmyLastschriften`. Sie liefert unbezahlte Rechnungen (`boolBezahlt <> -1`) aus Aufträgen mit den Lastschrift-Zahlungsbedingungen 3 oder 26. Die Formularlogik setzt standardmäßig einen Fälligkeitszeitraum von heute minus 30 Tagen bis heute und verarbeitet beim Sammelbutton alle angezeigten Datensätze nach einer Sicherheitsabfrage.

Für jede verarbeitete Rechnung wird das Bezahldatum auf `datFaelligkeitsDatum` gesetzt, `boolBezahlt = 1` geschrieben und `fBezahlterBetrag` nach der Access-Skonto-Logik ermittelt. Ausgangswert ist `fRechnungsbetrag`. Danach werden Skonto 1, 2 und 3 in dieser Reihenfolge geprüft; eine Stufe gilt, wenn ihr Betrag positiv und ihr Gültigkeitsdatum mindestens so groß wie das Fälligkeitsdatum ist. Weil die Prüfungen nacheinander erfolgen, überschreibt eine später gültige Stufe eine frühere.

Die Webanwendung stellt diese Funktion unter `/lastschriften` bereit. Vor dem Schreiben wird die aktuelle Auswahl mit Anzahl und Gesamtsumme angezeigt. Das Sammelupdate erfolgt in einer SQL-Server-Transaktion und prüft beim Schreiben erneut, dass die Rechnung noch unbezahlt ist. Es werden ausschließlich `datBezahlDatum`, `fBezahlterBetrag` und `boolBezahlt` geändert. `janus_connect` besitzt dafür nur spaltenbezogenes UPDATE auf genau diesen drei Feldern von `accountings.dbo.tblRechnung`.

### 9.6 Rechnungen ohne Umsatzsteuer

Die Access-Abfrage `qRechnungenOhneSteuernAb` fragt einen anonymen Parameter `[?]` für das früheste Rechnungsdatum ab und listet anschließend ausschließlich Datensätze aus `tblRechnung` mit `fBetrag > 0` und `fSteuer = 0`. Die Webanwendung stellt diese Prüfliste unter `/rechnungen-ohne-ust` bereit und ersetzt den unbeschrifteten Access-Parameter durch ein explizites Datumsfeld. Die Ansicht ist rein lesend und benötigt keine zusätzlichen SQL-Rechte.

### 9.7 Branchenübersicht und Branchenexport

Der Access-Bericht `Branchen` verbindet `tblKunde`, `tblKundenBranchen` und `tblBranchen` und zeigt die Kunden nach Branchenbezeichnung gruppiert mit Kundennummer, Name, Adresse und Telefon. Die Webanwendung stellt diese Übersicht unter `/branchen-auswertung` bereit und verlinkt die Kundennummer direkt in die Kundenansicht.

Der historische Branchenexport verwendet eine eigene, etwas schmalere Feldmenge mit Branchenbezeichnung, Kunden-ID, Kundenname, Telefax und Branchencode. Die Webanwendung übernimmt diese bestätigte Datenbasis, exportiert jedoch als echte `.xlsx`-Datei statt des alten `.xls`-Formats. Beide Funktionen sind rein lesend.

## 10. Dokumentationspflege

Die drei Dokumentationsziele werden im Classic-Frontend über eine linke Direktleiste und im Modern-Frontend über Direktbuttons in der Kopfleiste angeboten. Die Links verwenden `target="_blank"` mit `rel="noopener"` und öffnen daher bewusst einen neuen Browser-Tab statt eines internen Workspace-Fensters.

Diese Datei ist die technische Quelle für die spätere Projektdokumentation. Bei Änderungen an Architektur, Tabellen, Beziehungen, Rechten, Geschäftslogik oder Modulen muss sie zusammen mit dem Code aktualisiert werden.

Das Benutzerhandbuch wird parallel in `docs/HANDBUCH.md` gepflegt. Zusätzlich wird `docs/SQL_WIKI.md` als SQL-Statement-Wiki geführt. Dort werden bestätigte Access-Abfragen, fachlich relevante direkte SQL-Abfragen und Berechtigungs-Statements mit kurzer Erklärung gesammelt. Die drei Markdown-Dateien werden über feste Links in der Webanwendung angezeigt und können nach Abschluss der Migration als Word- oder PDF-Dokument ausgegeben werden. Die Dokumentationsansichten einschließlich SQL-Wiki besitzen in Classic und Modern eine Volltext-Suche mit Trefferanzahl sowie Vor-/Zurück-Navigation zwischen Fundstellen. Abschnitt 10 bleibt bewusst der letzte Hauptabschnitt dieser Datei; neue technische Themen werden davor eingeordnet.
