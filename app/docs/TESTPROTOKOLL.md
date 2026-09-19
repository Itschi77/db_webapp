# Testprotokoll und Fehleranalyse

Stand: 19.09.2026

Dieses Dokument ist das fortlaufende Abnahme- und Fehlerprotokoll der Migration der alten Buchhaltungs-/Rechnungsfunktionen in die Laravel-Webapp. Es dokumentiert nicht nur erfolgreiche Tests, sondern ausdrücklich auch gefundene Fehler, deren Ursache und die daraus abgeleiteten Schutzmaßnahmen.

## 1. Grundsatz

Für produktionsnahe Tests gelten folgende Regeln:

- Schreibende Funktionen bleiben bis zur gezielten Freigabe gesperrt.
- Vor dem ersten produktiven Rechnungsschreiben wurde ein gemeinsamer CARDEA-Backupstand aller drei produktiven Datenbanken erstellt.
- Tests verwenden soweit sinnvoll reale Daten und reale Dokumentvorlagen.
- Fehler werden nicht durch geratenen Ersatzwert „behoben“, sondern blockiert oder anhand der echten Datenquelle korrigiert.
- Nach jedem produktionsnahen Fehlerfall werden SQL-Zustand, erzeugte Dateien und Schutzmechanismen separat kontrolliert.

## 2. Datenbank- und Verbindungsprüfungen

### SQL Server 2019 / CARDEA

**Ziel:** Sicherstellen, dass die Webapp die drei produktiven Datenbanken erreicht.

**Geprüft:**

- `accountings`
- `domains`
- `topsnetdb_safe`
- Verbindung über `janus_connect`
- Tabellenzugriffe und Schreibrechte für die freigegebenen Rechnungstabellen

**Ergebnis:** Verbindungen funktionieren. Der Webapp-Benutzer besitzt nur die für die produktiven Abläufe vorgesehenen Rechte.

**Besonderheit:** Alle drei Datenbanken laufen derzeit auf Compatibility Level **100**, obwohl der Server SQL Server 2019 ist. SQL-Server-2019-Zielstufe wäre **150**. Dieses Thema wird separat kontrolliert migriert.

## 3. Backup-Systemtest

**Ziel:** Vor produktiven Schreibtests einen reproduzierbaren Sicherungsstand erzeugen.

**Durchgeführt:**

- COPY_ONLY
- CHECKSUM
- COMPRESSION
- Ablage direkt auf CARDEA
- Verifikation über `msdb`

**Bestätigter Pre-Release-Lauf:**

| Datenbank | Ergebnis |
| --- | --- |
| accountings | erfolgreich, ca. 82,35 GB logisch / 24,31 GB komprimiert |
| domains | erfolgreich |
| topsnetdb_safe | erfolgreich |

**Fehleranalyse während des Tests:** Ein synchron gestarteter großer Backup-Aufruf wirkte zunächst festhängend. Die SQL-Prüfung zeigte keinen aktiven BACKUP-Request mehr, während der Clientprozess noch wartete.

**Behebung:** Der produktive, dafür vorgesehene Queue-/Backup-Worker wurde als verbindlicher Ausführungsweg verwendet. Die drei Sicherungen wurden anschließend über den Worker erfolgreich abgearbeitet und in `msdb` bestätigt.

## 4. Rechnungstool: allgemeiner End-to-End-Test

**Ziel:** Repräsentative Rechnungsarten vollständig bis zur PDF-Vorschau prüfen, ohne produktiv zu schreiben.

### Bestätigte Fälle

| Fall | Auftrag | Zweck | Ergebnis |
| --- | ---: | --- | --- |
| Festpreis | 5946 | normaler Festpreis | bestanden |
| Vorausberechnung | 5054 | Vorausberechnung | bestanden |
| Staffel/Accounting | 5946 | Staffel-/Accountingpfad | bestanden |
| Domain | 5900 | Domainabrechnung | bestanden |
| mehrere Positionen | 5946 | Mehrpositionsfall | bestanden |
| Rabatt | 5418 | Rabattlogik | bestanden |

Zusätzlich wurden Rechnungsablage, MIDAS-read-only-Mount, Write-Guard und PDF-Renderer geprüft.

**Ergebnis:** 6/6 fachliche Fälle und 4/4 Systemprüfungen bestanden.

## 5. Historischer Paritätsvergleich

**Ziel:** Prüfen, ob die neue Weblogik historische Rechnungen cent- und zeilengleich reproduziert.

**Bestätigte Stichprobe:** Rechnungen `2026001462`, `2026001437`, `2026001456`, `2026001425`, `2026001461`, `2026001460`.

**Ergebnis:** Keine Netto-, Steuer-, Brutto- oder Zeilendifferenzen.

**Hinweis:** Bei historischen Vergleichen können heutige Stammdaten oder später geänderte Preise eine Abweichung verursachen, obwohl die damalige Rechnung korrekt war. Solche Fälle werden deshalb nicht automatisch als Programmfehler bewertet.

## 6. PDF-/Word-Vorlagentest

**Ziel:** Die originale Rechnungsvorlage möglichst originalgetreu übernehmen.

**Geprüft:**

- originale Word-Vorlage
- dynamische Rechnungspositionen
- mehrseitige Rechnung mit 24 Positionen
- wiederholte Tabellenköpfe
- Seitenumbrüche
- Fußzeile
- mehrzeilige Beschreibungen und Hinweise

**Gefundener Fehler:** Fußzeile und Umbrüche waren in frühen Fassungen nicht stabil.

**Behebung:** Word-XML und PDF-Erzeugung wurden angepasst; Tabellenzeilen erhielten kontrollierte Umbruchregeln und die Fußzeile wurde gegen unerwünschtes Wrapping gehärtet.

## 7. ZUGFeRD-Test

**Ziel:** Reale ZUGFeRD-Rechnung nach EN16931 erzeugen und technisch validieren.

**Bestätigter Fall:** Auftrag `5946`.

**Prüfungen:**

- XML-Schema
- EN16931-Semantik
- Einbettung in PDF
- PDF/A-3u
- veraPDF 1.30.2

**Ergebnis:** vollständig bestanden.

## 8. XRechnung-Test

**Ziel:** Reale XRechnung anhand eines Kunden mit Leitweg-ID erzeugen.

**Bestätigter Datensatz:** Auftrag `5652`, Kunde `4841`, Leitweg-ID `992-01497-46`.

**Gefundener Fehler:** Der reale Fall ist Lastschrift. KoSIT meldete fehlende Pflichtdaten:

- Mandatsreferenz
- Gläubiger-ID
- Konto-/Debited-Account-Angabe

**Ursache:** Diese Daten sind in den aktuell angebundenen Quellen nicht eindeutig hinterlegt.

**Behebung:** Die Webapp erzeugt bei diesem Fall **keine erfundenen Ersatzwerte**. Lastschrift-XRechnung wird vor Ausgabe blockiert, bis Mandatsreferenz und Gläubiger-ID als belastbare Datenquelle modelliert sind.

## 9. E-Rechnungs-Sonderfälle

### Negative Positionen

**Problem:** Negative Rechnungszeilen lassen sich nicht einfach wie normale Positionen als EN16931-konforme Rechnung ausgeben.

**Maßnahme:** E-Rechnung wird bei negativer Rechnungsposition derzeit blockiert. Später ist eine korrekte Allowance-/Charge-Abbildung erforderlich.

### 0 % Umsatzsteuer

**Problem:** 0-%-Steuer benötigt je nach Fall einen fachlich korrekten Steuerbefreiungsgrund.

**Maßnahme:** Kein geratenes Standardmerkmal. Der Fall bleibt als fachliche Erweiterung offen.

## 10. Mahnwesen-Endtest

**Ziel:** Mahnlogik realitätsnah mit echten offenen Rechnungen prüfen, ohne produktiv zu schreiben.

**Bestätigte Prüfungen:**

1. Mahnstufe 1
2. Mahnstufe 2
3. Mahnstufe 3
4. strittige Rechnung
5. Wiedervorlage
6. Ratenzahlung
7. Kundensperre
8. Mahnschreiben-PDF
9. produktiver Write-Guard

**Ergebnis:** 9/9 Prüfungen bestanden.

**Besonderheit:** Historische Daten enthalten auch Mahnstufe 4. Die neue automatische Logik endet bewusst bei Stufe 3.

## 11. Rechnungsschreib-Guard

**Ziel:** Sicherstellen, dass Rechnungsschreiben vor Produktivfreigabe unmöglich ist.

**Ergebnis:** Mit `INVOICE_WRITES_ENABLED=false` wird der Schreibpfad vor SQL-Änderungen blockiert.

**Produktivfreigabe:** Nach Backup und erfolgreichem Endtest wurde Rechnungsschreiben kontrolliert freigegeben. Mahnwesen blieb weiterhin deaktiviert.

## 12. Erste kontrollierte Produktivrechnung

**Auftrag:** `5946`  
**Kunde:** `6432`  
**Rechnung:** `2026001465`  
**interne Rechnungs-ID:** `148798`  
**Accounting:** ausgeschaltet  
**Netto:** 9,90 €  
**USt.:** 1,88 €  
**Brutto:** 11,78 €

### Ergebnis der Buchung

- Rechnungsnummer korrekt reserviert
- `tblRechnung` korrekt geschrieben
- genau eine Zeile in `tblAuftragPosBerechnet`
- Fälligkeit 26.09.2026
- PDF unter `\\janus\Rechnungen\2026\2026001465.pdf`
- ZUGFeRD erzeugt

### Gefundener Fehler: PLZ und Ort vertauscht

Im erzeugten PDF stand:

`Erpel 53579`

statt:

`53579 Erpel`

### Fehleranalyse

Die PDF-Logik hatte PLZ und Ort nicht vertauscht. Die Stammdaten selbst waren in beiden Quellen falsch:

- `topsnetdb_safe.dbo.tblKunde`
- `accountings.dbo.tblRechnungsanschrift`

Gespeichert war:

- `strPLZ = Erpel`
- `strOrt = 53579`

Eine Bestandsprüfung fand insgesamt acht Rechnungsanschriften mit demselben Verdachtsmuster.

### Sofortmaßnahme

`INVOICE_WRITES_ENABLED` wurde unmittelbar wieder auf `false` gesetzt. Dadurch konnte keine weitere Rechnung erzeugt werden.

### Rollback-Prüfung

Ein gezielter DELETE-Rollback wurde innerhalb einer SQL-Transaktion versucht. Der Benutzer `janus_connect` besitzt bewusst keine DELETE-Berechtigung auf `tblAuftragPosBerechnet`.

**Ergebnis:** SQL Server verweigerte den DELETE. Die gesamte Rollback-Transaktion wurde automatisch vollständig zurückgerollt. Rechnung, Positionszeile und Nummernkreis blieben unverändert und konsistent.

**Entscheidung:** Die Sicherheitsrechte wurden nicht aufgeweicht.

### Korrektur

Für Kunde `6432` wurden Kundenstamm und Rechnungsanschrift auf:

- PLZ `53579`
- Ort `Erpel`

korrigiert.

Die bestehende Rechnung wurde anschließend mit **derselben Rechnungsnummer 2026001465** aus dem bereits gebuchten historischen September-Zustand neu gerendert. Es wurde keine zweite Rechnung erzeugt.

Der fehlerhafte Originalbeleg wurde außerhalb der Rechnungsfreigabe als Rollback-Nachweis archiviert.

### Nachkontrolle

- PDF enthält `53579 Erpel`
- alter Text `Erpel 53579` nicht mehr vorhanden
- gespeicherte Positionszeile = historische Neuberechnung
- Position 36984
- Berechnungstag 01.09.2026
- Netto 9,90 €
- Steuer 1,88 €
- Brutto 11,78 €
- ZUGFeRD XSD gültig
- EN16931 gültig
- PDF/A-3u gültig
- veraPDF 1.30.2 ohne Fehler

### Dauerhafte Schutzmaßnahme

Eine neue zentrale Rechnungsanschrift-Plausibilitätsprüfung blockiert einen Auftrag, wenn:

- `strPLZ` Text enthält und
- `strOrt` ausschließlich numerisch ist.

Die Regel läuft:

- im Auftragstestlauf,
- damit unmittelbar vor produktivem Schreiben,
- zusätzlich in der täglichen Kunden-/Rechnungsanschrift-Konsistenzprüfung.

Bei Einführung blieben zwei aktive Rechnungstool-Aufträge mit diesem Muster:

- Auftrag `5602`, Kunde `6342`: Bonn / 53177
- Auftrag `5926`, Kunde `6426`: Meckenheim / 53340

Diese Datensätze werden nicht automatisch verändert.

## 13. Dokumentations-PDF-Test

**Ziel:** Alle Dokumentationen druckbar und als PDF exportierbar machen.

**Geprüft:**

- Benutzerhandbuch
- Rechnungstool-Handbuch
- Technische Dokumentation
- SQL-Statement-Wiki

**Ergebnis:** PDF-Erzeugung erfolgreich. Automatisierter Test prüft HTTP-Status, MIME-Type und PDF-Header.

## 14. Automatisierte Testsuite

Nach Einführung der Adressprüfung:

**17 Tests / 67 Assertions bestanden.**

Abgedeckt sind unter anderem:

- Dokumentations-PDFs
- Mahnlogik
- Rechnungsschreib-Guard
- Dokumentbearbeitung
- E-Rechnung
- Word-Vorlage
- PLZ-/Ort-Plausibilitätsprüfung

## 15. Offene bzw. bewusst getrennte Punkte

- XRechnung + Lastschrift: Mandatsreferenz und Gläubiger-ID
- 0-%-USt.-E-Rechnung: fachlicher Steuerbefreiungsgrund
- negative E-Rechnungspositionen: EN16931-konforme Allowance-/Charge-Modellierung
- kontrollierte Produktivfreigabe Mahnwesen
- zwei aktive Aufträge mit verdächtig vertauschter PLZ/Ort-Zuordnung
- SQL-Server-Datenbanken derzeit Compatibility Level 100; kontrolliertes Upgrade auf Level 150 ist noch ausstehend

## 16. Pflege dieses Protokolls

Dieses Dokument wird bei weiteren Abnahme-, Fehler- und Produktivtests fortgeschrieben. Ein Test gilt erst dann als abgeschlossen, wenn Ergebnis, gefundene Auffälligkeiten und gegebenenfalls die daraus entstandene Schutzmaßnahme dokumentiert sind.
