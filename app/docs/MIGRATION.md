# Technische Dokumentation – DB-Webmigration

Stand: 14.09.2026

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
| 4 | Fremdaccounting | separat / noch nicht vollständig migriert |
| 5 | Dialin-Zeitabrechnung | `tblAnbindungDialin` |
| 6 | Domain-Accounting | `accountings.dbo.tblDomains` |
| 7 | SMS-Service | Altbestand, Referenz noch nicht vollständig migriert |

Neue Anbindungen werden nur für die ausreichend verstandenen Typen 1, 2, 3, 5 und 6 angeboten. Typ 7 (SMS) ist strikt lesender Altbestand und kann weder neu angelegt noch geändert werden. Typ 4 bleibt bis zur Migration des Fremdaccountings separat.

## 6. Aktuell umgesetzte Funktionen

- Kunden suchen, anzeigen, anlegen und bearbeiten
- Branchen-Zuordnung
- Ansprechpartner anlegen und bearbeiten
- Rechnungsanschriften anlegen und bearbeiten
- offene Rechnungen anzeigen und einzelne Rechnungen aus der Kundenansicht öffnen
- Aufträge suchen, anzeigen, anlegen und bearbeiten
- Auftragspositionen anlegen und bearbeiten
- Produktdaten bei Auftragspositionen übernehmen
- Ticket-Mail aus einem Auftrag vorbereiten
- Anbindungen je Auftragsposition anzeigen, anlegen und bearbeiten
- Referenzsuche für Netz, Port, Dialin und Domain über verständliche Vorschlagslisten statt reiner ID-Eingabe
- SMS-Anbindungen (Typ 7) ausschließlich als nicht editierbaren Altbestand anzeigen
- zentrale Aufträge-Wiedervorlage mit Filtern für fällige, zukünftige und alle Einträge
- globale Rechnungsverwaltung mit Suche, Offen/Bezahlt-Filter und Rechnungsdetail (lesend)
- Classic-/Modern-Frontend umschaltbar
- Access-artiger Mehrfenster-Arbeitsbereich im Browser

## 7. Wiedervorlage und Rechnungen

Die Wiedervorlage basiert auf `accountings.dbo.tblAuftragPos.datWiedervorlageVertrieb`. Der historische Platzhalter `01.01.1980` wird nicht als echte Wiedervorlage behandelt. Die zentrale Liste verknüpft Position -> Auftrag -> Kunde und erlaubt direkte Navigation in Auftrag und Position.

Die globale Rechnungsverwaltung liest `accountings.dbo.tblRechnung` und verknüpft über `tblRechnung.intAufNr -> tblAuftrag.intAufNr -> tblKunde.intID`. Sie ist derzeit bewusst read-only. Angezeigt werden unter anderem Rechnungsnummer, Status, Fälligkeit, Zahlbetrag, Mahnstufe, strittige Rechnungen sowie das Rechnungsdetail.

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

## 10. Dokumentationspflege

Diese Datei ist die technische Quelle für die spätere Projektdokumentation. Bei Änderungen an Architektur, Tabellen, Beziehungen, Rechten, Geschäftslogik oder Modulen muss sie zusammen mit dem Code aktualisiert werden.

Das Benutzerhandbuch wird parallel in `docs/HANDBUCH.md` gepflegt. Beide Markdown-Dateien werden über feste Links in der Webanwendung angezeigt und können nach Abschluss der Migration als Word- oder PDF-Dokument ausgegeben werden.
