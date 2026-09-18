# Technische Dokumentation – DB-Webmigration

Stand: 17.09.2026

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

### 2.1 Systemvoraussetzungen und Basissoftware

Die Webanwendung läuft auf `janus` mit Debian 13. Für den Betrieb und die Anbindung an die bestehende Windows-Domäne werden neben Docker/Caddy/Laravel zusätzliche Systemkomponenten benötigt.

**Betriebssystem und Grundkomponenten:**
- Debian 13 auf `janus.topsnet-ads.tops.net`
- korrekte Zeitsynchronisation via NTP, da Kerberos auf geringe Zeitabweichungen angewiesen ist
- funktionierende DNS-Auflösung der Active-Directory-Domäne `topsnet-ads.tops.net` und ihrer Domaincontroller
- Docker Compose für die Laravel-/PostgreSQL-Anwendungscontainer
- Caddy als äußerer HTTPS-Reverse-Proxy

**Active Directory, SSSD und Kerberos:**
- `realmd`, `sssd`, `sssd-ad`, `sssd-tools`, `adcli`, `krb5-user`, `libnss-sss`, `libpam-sss` und `packagekit` für Domänenbeitritt und Identitätsauflösung
- `janus` ist Mitglied der Active-Directory-Domäne `topsnet-ads.tops.net`
- Benutzer- und Gruppenauflösung erfolgt über SSSD
- `DB-Webapp-Users` ist die Berechtigungsgruppe für die gesamte Webanwendung
- `DB-Webapp-Rechnungstool` ist die zusätzliche Berechtigungsgruppe für die Fakturierung

Für den Webdienst ist der Kerberos-Service-Principal `HTTP/db-webapp.topsnet-ads.tops.net` auf dem AD-Rechnerkonto `JANUS$` registriert. Wichtig ist die Zweiteilung: Der SPN muss sowohl im AD-Objekt (`servicePrincipalName`) als auch in einem passenden Keytab vorhanden sein. Ein lokaler Keytab-Eintrag allein reicht nicht, weil der KDC den Dienst sonst nicht kennt. Secrets und Keytab-Inhalte werden nicht im Repository dokumentiert oder versioniert.

### 2.2 Windows-SSO / SPNEGO

Für die Windows-SSO-Anmeldung ist Nginx mit `libnginx-mod-http-auth-spnego` als lokaler Auth-Proxy auf `127.0.0.1:8081` eingerichtet. Caddy bleibt der öffentliche HTTPS-Endpunkt. Nginx authentifiziert den Windows-Benutzer per Kerberos/SPNEGO und reicht die bestätigte Identität anschließend an die Anwendung weiter. Das bloße Verbergen von Schaltflächen gilt ausdrücklich nicht als Zugriffskontrolle.

Für Nginx wird nicht der vollständige Maschinen-Keytab verwendet. Aus `/etc/krb5.keytab` wurde ein separater `/etc/nginx/db-webapp-http.keytab` erzeugt, der ausschließlich den Principal `HTTP/db-webapp.topsnet-ads.tops.net@TOPSNET-ADS.TOPS.NET` enthält und nur für `root:www-data` lesbar ist. Damit erhält der Webserver keinen Zugriff auf weitere Host-/WSMAN-/TERMSRV-Principals des Rechners.

Der lokale SPNEGO-Test ist bestätigt: Ein Kerberos-Ticket des AD-Benutzers kann für den HTTP-SPN bezogen werden; ein `curl --negotiate` gegen den lokalen Nginx-Proxy liefert nach erfolgreichem Negotiate-Handshake HTTP 200. Die Zielkette ist produktiv: Browser -> Caddy/HTTPS -> Nginx/SPNEGO -> Laravel.

Für die serverseitige AD-Gruppenautorisierung läuft der unter `ops/ad-authz` abgelegte lokale Helper als Systemdienst auf `127.0.0.1:8090`. Er übernimmt den bereits per Kerberos authentifizierten Principal aus `X-Remote-User` und prüft die tatsächliche SSSD-Gruppenmitgliedschaft über die lokale NSS-Auflösung. Freigegeben sind ausschließlich die Gruppen `DB-Webapp-Users`, `DB-Webapp-Rechnungstool` und `DB-Webapp-Admins`; beliebige AD-Gruppen können über den Helper nicht abgefragt werden. Der Helper kann mehrere freigegebene Gruppen in einer Anfrage prüfen; alle angeforderten Gruppen müssen vorhanden sein. Nginx bindet die Prüfung über `auth_request` ein. Für die gesamte Webanwendung wird `DB-Webapp-Users` erzwungen. Der Pfad `/fakturierung` wird zusätzlich über eine zweite Authz-Subrequest auf `DB-Webapp-Rechnungstool` geschützt, sodass dort beide Gruppen erforderlich sind. Ohne Kerberos-Ticket erfolgt HTTP 401, ohne benötigte Gruppenmitgliedschaft HTTP 403.

Nginx reicht den bestätigten AD-Principal als `X-Remote-User` an Laravel weiter. Zusätzlich liefert der Authz-Helper bei vorhandener Rechnungstool-Mitgliedschaft den internen Header `X-DB-Webapp-Rechnungstool: 1`; Nginx setzt diesen Header kontrolliert für Laravel. Die Anwendung übernimmt daraus den angemeldeten Kurznamen und die Rechnungstool-Berechtigung. Der Laravel-Middleware-Schutz `invoice.access` verweigert `/fakturierung` ebenfalls mit HTTP 403, wenn der interne Berechtigungsheader fehlt. Damit wird der Menüstatus nicht als Sicherheitsgrenze verwendet, sondern nur als Bedienhinweis.

Für `/admin` gilt zusätzlich die AD-Gruppe `DB-Webapp-Admins`. Der Authz-Helper akzeptiert diese Gruppe ausschließlich als fest freigegebene Gruppe und setzt nach erfolgreicher SSSD-Prüfung `X-DB-Webapp-Admin: 1`. Nginx schützt `/admin` mit einer eigenen Subrequest; Laravel prüft denselben internen Header nochmals über `admin.access`. Der Adminbereich verwaltet SQL-Server-, PostgreSQL-, SMTP-, Dateisystem- und HTTP-Verbindungsprofile, führt Verbindungstests aus und zeigt die letzten 500 Zeilen vorhandener Laravel-Logs. Secrets liegen per Laravel-Encrypted-Cast verschlüsselt in PostgreSQL. Ein geändertes Profil wird erst nach erneutem erfolgreichen Test als Laufzeitkonfiguration verwendet; andernfalls bleibt die `.env`-Konfiguration der sichere Fallback.

Für echtes browserseitiges Single Sign-on ist der Host `db-webapp.topsnet-ads.tops.net` per Gruppenrichtlinie für integrierte Windows-Authentifizierung freigegeben. Die dafür verwendete GPO heißt `DB-Webapp Browser SSO` und ist in der Domäne `topsnet-ads.tops.net` verknüpft. Die Browserrichtlinien werden als Computerrichtlinien verteilt. Für Microsoft Edge und Google Chrome wird `AuthServerAllowlist` mit genau diesem Host verwendet; für Firefox ist unter `Authentication` die `SPNEGO`-Freigabe auf denselben Host gesetzt. Die Richtlinien wurden auf einem Domänenclient in Edge (`edge://policy`), Chrome (`chrome://policy`) und Firefox (`about:policies`) als aktiv bestätigt. Nach Aktualisierung der Gruppenrichtlinien funktioniert der Zugriff in allen drei Browsern über Kerberos/SPNEGO. Eine Kerberos-Delegation (`AuthNegotiateDelegateAllowlist`) ist für diese Anwendung nicht erforderlich, da keine Benutzeranmeldedaten an nachgelagerte Dienste delegiert werden.

Für die HTTPS-Vertrauenskette nutzt Caddy `tls internal`. Die dabei verwendete Root-CA wird per GPO `DB-Webapp Caddy CA Trust` als Computerrichtlinie unter `Vertrauenswürdige Stammzertifizierungsstellen` verteilt. Auf dem Testclient `SELENE` ist die GPO laut RSOP angewendet; die Root-CA `CN=Caddy Local Authority - 2026 ECC Root` ist im Zertifikatsspeicher `LocalMachine\\Root` vorhanden. Das von `db-webapp.topsnet-ads.tops.net` ausgelieferte Zertifikat wird von `Caddy Local Authority - ECC Intermediate` signiert und vom Client nach der GPO-Verteilung ohne Zertifikatswarnung akzeptiert. Verteilt wird ausschließlich das öffentliche Root-Zertifikat, niemals ein privater Schlüssel.

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
- `tblPort` und `tblAnbindungNetze`: SELECT, INSERT, UPDATE; kein DELETE
- `tblAnbindungDialin` und `tblDomains`: derzeit nur lesend

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

### 9.8 Accounting-Berichte

Die drei historischen Reports **Accountings ohne Zusatzinfos**, **Accountings mit Zusatzinfos und Zusatzsumme** und **Accountings mit Zusatzinfos ohne Zusatzsumme** werden unter `/accounting-berichte` bereitgestellt. Sie wurden über vorhandene VBA-Ereignisprozeduren und Reportdefinitionen rekonstruiert; in der aktuellen Access-Hauptmaske existieren dafür jedoch keine sichtbaren Buttons. Deshalb werden sie im Classic- und Modern-Hauptmenü nicht als eigenständige Menüpunkte angezeigt. Alle Varianten verwenden Kundennummer, Monat und Jahr als Parameter und sortieren die Monatsauswertung nach `decGesamt` absteigend. Die Variante ohne Zusatzinfos berücksichtigt nur `boolAbrechenbar <> 0` und leitet den Kunden über Auftrag und Auftragsposition her. Die beiden Varianten mit Zusatzinfos verwenden `tblAnbindungen.intKID` direkt und zeigen zusätzlich Hinweise zu abweichendem Abrechenbar-Start/-Ende sowie bei Dialin-Typ 3 die monatliche Verbindungszeit.

Die Verbindungszeit entspricht der Access-Abfrage `Sekunden pro Anbindung`: Für Typ 3 werden `tblAnbindungenDialinWerte.intVerbindungsdauerInSec` innerhalb des ausgewählten Kalendermonats je Anbindung summiert. **Mit Zusatzsumme** summiert alle angezeigten Datensätze; **ohne Zusatzsumme** summiert entsprechend der Access-Fußformeln nur abrechenbare Datensätze. Alle drei Berichte sind read-only. Für die Dialin-Sekunden benötigt `janus_connect` zusätzlich ausschließlich `SELECT` auf `accountings.dbo.tblAnbindungenDialinWerte`.

### 9.9 Produktpflege

Das Access-Formular **Alle Produkte mit Eigenschaften** arbeitet direkt auf `accountings.dbo.tblProdukt`. Die Webanwendung stellt die Produktpflege unter `/produkte` bereit. Vorhandene Produkte können gesucht und bearbeitet, neue Produkte angelegt werden. Löschen wird bewusst nicht angeboten. Die Sortierung der Produktliste orientiert sich am Access-Formular und verwendet `intID` absteigend.

Gebundene Felder sind unter anderem `strKuerzel`, `strBeschreibung`, `fPreis`, `intAbrechnungsArt`, `intMengenSchluessel`, `intDatevBezeichnungsID`, `intProduktGruppe`, `intMwstSchluesselID`, `intStaffeltyp`, `intStaffelgruppeID`, `boolIstAnbindung`, `boolProduktInaktiv` sowie die beiden maximalen Rabattfelder. Neue Datensätze erhalten serverseitig eine neue `rowguid` mittels `NEWID()`.

Die Staffeltyp-Logik folgt dem bestätigten Access-VBA: Typ 1 verwendet `tblStaffelgruppe`, Typ 2 `tblLinearStaffel`, Typ 3 `tblZeittarife`, Typ 5 die Domain-Konditionen aus der separaten Datenbank `domains.dbo.tblDomainKonditionen` und Typ 6 `tblBereichsStaffel`. Typ 0 und 4 verwenden keine sichtbare Staffelgruppenauswahl. Beim Wechsel des Staffeltyps auf Domain-Accounting (Typ 5) wird wie in `Kombinationsfeld41_Change` der Mengenschlüssel auf ID 1 festgelegt. Bereits bestehende Typ-5-Datensätze werden beim bloßen Öffnen nicht stillschweigend umgeschrieben. Die Domain-Konditionspflege selbst ist noch nicht migriert.

`janus_connect` besitzt für `tblProdukt` SELECT, INSERT und UPDATE, jedoch kein DELETE. Die Referenztabellen werden ausschließlich gelesen. Für `domains.dbo.tblDomainKonditionen` besteht nur SELECT.

### 9.10 Staffelgruppen und Staffelrechner

Das Access-Formular `Staffelgruppen` verwendet `tblStaffelgruppe` als Hauptquelle. Das eingebettete Unterformular liest `tblStaffelpreise` und ist über `tblStaffelgruppe.intID` zu `tblStaffelpreise.intStaffelgruppeID` verknüpft. Die Preisstufen werden nach `intMenge` sortiert.

Die Webanwendung stellt die Pflege unter `/staffelgruppen` bereit. Staffelgruppen und Preisstufen können angelegt und bearbeitet werden; DELETE wird bewusst nicht freigegeben. Neue Zeilen erhalten eine `rowguid` mit `NEWID()`.

Der historische `ufrmStaffelrechner` schreibt über `Befehl13_Click` in `tblStaffelPreise`. Die bestätigte Reihenfolge wird nachgebildet: Startwert und Startpreis werden eingelesen, solange der aktuelle Wert kleiner als der Endwert ist werden zuerst Schrittweite und Schrittpreis addiert und anschließend `intMenge`, `intvkPreis` und `intStaffelgruppeID` geschrieben. Das Web führt den Batch transaktional aus und begrenzt eine einzelne Generierung aus Betriebssicherheitsgründen auf 10.000 neue Zeilen. Bereits vorhandene Preisstufen werden nicht gelöscht oder ersetzt.

`janus_connect` besitzt für `tblStaffelgruppe` und `tblStaffelpreise` SELECT, INSERT und UPDATE, jedoch kein DELETE.


### 9.11 Linearstaffeln

Das Access-Formular `frmLinearstaffel` arbeitet direkt auf `accountings.dbo.tblLinearStaffel`. Es besitzt keine Unterformulare oder zusätzliche Rechenlogik. `NeuStaffel_Click` wechselt lediglich auf einen neuen Datensatz und fokussiert `strBezeichnung`; `Schluss_Click` schließt das Formular.

Die Webanwendung stellt die Pflege unter `/linearstaffeln` bereit. Bearbeitet werden `intMengeFrei`, `floatPreisEinheit`, `strBezeichnung`, `floatBasisPreis` und `strAbrechnungseinheit`. Neue Datensätze erhalten eine `rowguid` per `NEWID()`. `janus_connect` besitzt SELECT, INSERT und UPDATE auf `tblLinearStaffel`, jedoch kein DELETE.


### 9.12 Zeittarife

Das Access-Formular für Zeittarife verwendet `accountings.dbo.tblZeittarife` als Hauptquelle. Das Unterformular basiert auf `tblZeittarifeZonen` und ist über `tblZeittarife.intID` zu `tblZeittarifeZonen.intTarifID` verknüpft. Bestätigte Tarif-Felder sind `strTarifname`, `intFreiSekunden`, `intMindestAbnahmeSekunden` und `intTaktSekunden`; die Zonen enthalten `datBeginn`, `datEnde` und `fMinutenpreis`. Für Haupt- und Unterformular wurden keine Access-Ereignisprozeduren festgestellt.

Die Webanwendung stellt die Pflege unter `/zeittarife` bereit. Tarife und Zeitfenster können angelegt und bearbeitet werden; DELETE ist bewusst nicht freigegeben. Neue Datensätze erhalten `rowguid = NEWID()`. Die historischen Access-Uhrzeitwerte werden als SQL-Server-`datetime` mit Basisdatum `1899-12-30` gespeichert, im Web aber als Uhrzeit mit Sekunden bearbeitet.

### 9.13 Bereichsstaffeln

Das Access-Hauptformular verwendet `accountings.dbo.tblBereichsStaffel` mit Sortierung nach `strBezeichnung`. Das Unterformular `tblBereichsStaffelPreise Unterformular` liest `tblBereichsStaffelPreise` und ist über `tblBereichsStaffel.intID` zu `tblBereichsStaffelPreise.intStaffelGruppenID` verknüpft. Für das Hauptformular wurden keine Ereignisprozeduren festgestellt.

Die Webanwendung stellt die Pflege unter `/bereichsstaffeln` bereit. Kopfdatensätze und Detailbereiche können angelegt und bearbeitet werden; DELETE bleibt bewusst gesperrt. Neue Datensätze erhalten `rowguid = NEWID()`. Die Detailfelder sind `fGrundgebuehr`, `fBereichsGrundgebuehr`, `fStueckpreis`, `intMengeAb` und `intMengeBis`. `janus_connect` besitzt SELECT, INSERT und UPDATE auf `tblBereichsStaffel` sowie `tblBereichsStaffelPreise`, jedoch kein DELETE.

### 9.14 Bandbreiten-Tarife (#4/#7)

Das Access-Formular `frmBandbreitenTarife` verwendet `accountings.dbo.tblBandbreiteStaffel` als Hauptquelle. Das Unterformular `tblBandbreiteStaffelPreise Unterformular` verwendet `tblBandbreiteStaffelPreise`, sortiert nach `intMenge`, und ist über `tblBandbreiteStaffel.intID` zu `tblBandbreiteStaffelPreise.intStaffelGruppenID` verknüpft. Sichtbare Preisfelder sind `intMenge` (kBit/Sekunde) und `fVkPreis` (Nettopreis). Für das bestätigte Hauptformular wurden keine Access-Ereignisprozeduren festgestellt; insbesondere wird für **Automatisch berechnen…** keine nicht belegte Berechnungslogik erfunden.

Die Webanwendung stellt die Pflege unter `/bandbreitentarife` bereit. Tarifköpfe und Preiszeilen können angelegt und bearbeitet werden, neue Datensätze erhalten `rowguid = NEWID()`. `janus_connect` besitzt SELECT, INSERT und UPDATE auf `tblBandbreiteStaffel` sowie `tblBandbreiteStaffelPreise`, jedoch kein DELETE. Das Access-Hauptmenü bezeichnet denselben Pflegebereich als Abrechnungsart #4/#7 (MAX oder SUM); eine darüber hinausgehende MAX-/SUM-Logik ist in dem bestätigten Pflegeformular nicht hinterlegt.

### 9.15 Domainkonditionen (#5)

Das Access-Formular `frmDomainsKonditionen` arbeitet auf `domains.dbo.tblDomainKonditionen`. Bestätigte Felder sind `intID`, `strKonditionsName`, `intR_AbrechnungEinheit`, `intR_AbrechnungIntervall`, `fR_IntervallPreis`, `intEnthaltenAnzahl`, `intEnthaltenEinheit`, `fEinrichtungsPreis`, `strEinrichtungRechnungsInfo`, `boolFuerKonnektierung`, `boolFuerSecondary` und `boolVeraltet`. Für das Formular wurden keine Ereignisprozeduren festgestellt.

Die reguläre Intervall-Werteliste lautet 4=Tage, 5=Wochen, 6=Monate, 7=Jahre. Die zweite Werteliste für `intEnthaltenEinheit` ist historisch anders belegt: 4=Tage, 5=Monate, 6=Wochen, 7=Jahre. Der gespeicherte Access-Filter `strKonditionsName Like "*prime*"` wird im Web nicht zwangsweise angewendet, weil das bestätigte Access-Formular sichtbar ungefilterte Datensätze wie `Inklusivdomain` zeigt. Stattdessen steht eine Suche nach ID oder Konditionsname zur Verfügung.

Die Webpflege liegt unter `/domainkonditionen` und verwendet die separate SQL-Server-Verbindung `sqlsrv_domains`. `janus_connect` besitzt SELECT, INSERT und UPDATE auf `domains.dbo.tblDomainKonditionen`, jedoch kein DELETE.

### 9.16 SMS-Zugänge

Das Access-Formular `frmSMSZugaenge` arbeitet direkt auf `accountings.dbo.tblSMSZugaenge`; bestätigte Ereignisprozeduren gibt es nicht. Das Webmodul steht unter `/sms-zugaenge` bereit und bildet Single- sowie Corporate-Accounts ab. `boolIsCustomerAccount = 0` entspricht Single Account, `1` entspricht Corporate Account.

Besonders behandelt werden `strKennwort` und `strGWRegistrationPassword`: bestehende Kennwörter werden nie in die Weboberfläche zurückgelesen. Beim Bearbeiten bleibt ein vorhandenes Kennwort unverändert, solange kein neuer Wert eingegeben wird. Neue Datensätze erhalten `datErstelltAm` mit dem aktuellen Zeitpunkt und `strErstelltVon = 'webapp'`; DELETE bleibt gesperrt. `janus_connect` besitzt SELECT, INSERT und UPDATE auf `tblSMSZugaenge`.

### 9.17 Anbindungen / Verbindungen

Das Access-Formular `frmAnbindungen` basiert auf `tblAnbindungen` und verknüpft über `intAuftragsPos` zu Auftragsposition, Auftrag und Kunde. `intTyp` steuert sieben Accounting-Arten. Die technischen Unterformulare sind im Hauptformular read-only und werden abhängig vom Typ eingeblendet. Typ 1 referenziert `tblAnbindungNetze.intID`, Typ 2 `tblPort.intID`, Typ 3 und 5 `tblAnbindungDialin.intID`, Typ 6 `tblDomains.intID` und Typ 7 `tblSMSZugaenge.intSMSZugaengeID` über `tblAnbindungen.intAnbindungReferenz`. Fremd-Accounting (Typ 4) ist ein Sonderfall und verknüpft `tblAnbindungen.intID` mit `tblAnbindungAuswertung.intAnbindungID`.

Die Webanwendung stellt die zentrale Pflege unter `/anbindungen` bereit und erhält daneben die bereits vorhandene positionsbezogene Navigation. Kopfdatensätze können angelegt und bearbeitet werden, DELETE bleibt gesperrt. Das Abrechnungsende behält Uhrzeiten sekundengenau; bei einer reinen Datumsangabe wird wie im Access-VBA 23:59:59 verwendet. Sensible Dialin-Kennwörter werden nicht selektiert oder angezeigt. Die SNMP-Community wird in der zentralen Detailanzeige ebenfalls nicht ausgegeben. Der gespeicherte Access-Filter auf `strKopieRechnungsinfo Like "*crisis*"` wird nicht automatisch erzwungen.

Die Classic-Maske bildet `frmAnbindungen` jetzt als direkte Einzelmaske ohne vorgeschaltete Ergebnisliste ab. Beim Aufruf von `/anbindungen` wird unmittelbar ein Datensatz angezeigt; die Access-artige Navigationsleiste ermöglicht erster/zurück/weiter/letzter Datensatz und zeigt `n von m`. Diese Datensatznavigation lädt den nächsten Datensatz innerhalb desselben Workspace-Fensters und erzeugt kein weiteres Fenster. Graue Read-only-Felder für Anbindungs-ID, technische Unterformularwerte, Rechnungsinfo, Kunde, Auftrag und Auftragsposition öffnen per Rechtsklick oder Doppelklick ein Feldfilter-Menü. Die Operatoren Gleich/Nicht gleich, Beginnt mit/Beginnt nicht mit, Enthält/Enthält nicht und Endet mit/Endet nicht werden serverseitig parametrisiert umgesetzt. Der Filter bleibt beim Blättern erhalten und kann über Alle Filter entfernen zurückgesetzt werden. Zusätzliche Direktlinks von einer Anbindung in die Pflege von Netz, Port oder Dialin existieren nur im Modern-Frontend; die Classic-Ansicht bleibt bewusst näher am historischen Access-Formular. Die moderne Ergebnisliste versieht ihre Detaillinks mit einer expliziten UI-Kennung, damit beim Öffnen nicht versehentlich ein bereits zwischengespeichertes Classic-Fenster derselben Anbindung wiederverwendet wird.

### 9.18 Netze

Das Access-Formular `frmNetze` arbeitet direkt auf `accountings.dbo.tblAnbindungNetze`. Bestätigte Felder sind `intID`, `strNetzwerk`, `intNetzmaske`, `intGatewayRouter`, `intKundenNetz`, `intAccountingEingerichtet`, `intInUse`, `strVerwendung`, `strBemerkung`, `strStandort`, `strrechnungsinfo` und `rowguid`. Das Formular besitzt keine allgemeinen Ereignisprozeduren; **Neues Netz** springt lediglich auf einen neuen Datensatz und setzt den Fokus auf `strNetzwerk`, **Schliessen** beendet das Formular.

Die Webpflege liegt unter `/netze`. Die Classic-Datensatznavigation bleibt beim Blättern innerhalb desselben Workspace-Fensters. `intID` ist Identity, `rowguid` wird bei Neuanlage mit `NEWID()` gesetzt. Die drei Access-Checkboxfelder sind SQL-Integer und enthalten im Altbestand `-1`, `0` oder `NULL`; bei der Anzeige gilt ungleich 0 als aktiv, beim Speichern werden `-1` und `0` verwendet. `janus_connect` besitzt SELECT, INSERT und UPDATE auf `tblAnbindungNetze`, jedoch kein DELETE.

### 9.19 Ports

Das Access-Formular `frmPorts` arbeitet direkt auf `accountings.dbo.tblPort`. Bestätigt sind `intid`, `strRouterIP`, `strSNMPCommunity`, `strMIBVarIN`, `strMIBVarOUT`, `strPortDescription`, `strrechnungsinfo`, `decOverrunLimit`, `boolDeaktiviert`, `strMIBVarDESCR`, `strIfDescrMust`, `strIfDescrCurrent`, `dateIfDescrCurrent` und `rowguid`. `intid` ist Identity; neue Datensätze erhalten `NEWID()` als rowguid.

Im Access-Formular blendet `Form_Current` die beiden Kombinationsfelder für Portbeschreibung und SNMP-Community aus; `Neuer Port` blendet sie beim neuen Datensatz ein. Im Web werden diese Eingabehilfen nur beim Anlegen angeboten. Die Portbeschreibungs-Werteliste entspricht Access; SNMP-Community-Werte werden aus bestehenden Portdaten geladen und nicht als Secrets in Repository oder Dokumentation geschrieben. `strIfDescrCurrent` und `dateIfDescrCurrent` bleiben read-only. `janus_connect` besitzt SELECT, INSERT und UPDATE auf `tblPort`, jedoch kein DELETE.

### 9.20 Dialins

Das Access-Formular `frmAnbindungDialin` basiert auf `accountings.dbo.tblAnbindungDialin`. Die Webpflege verwendet bewusst keine `SELECT *`-Abfrage, sondern selektiert ausschließlich die benötigten Nicht-Geheimnis-Felder. `strKennwort` wird bei bestehenden Datensätzen nie gelesen oder angezeigt. Ein leeres Kennwortfeld lässt den vorhandenen Wert unverändert; nur eine bewusste neue Eingabe schreibt `strKennwort`. Neue Datensätze benötigen ein Kennwort und erhalten `NEWID()` als `rowguid`.

Das Unterformular `frmEinwahlnummern` ist über `Verknüpfen nach = intID` und `Verknüpfen von = intDID` angebunden. Die Tabelle `tblAnbindungDialinEinwahlnummern` enthält `intID`, `intDID`, `intEinwahlnummerID`, `datBeginn`, `datEnde` und `rowguid`. Neue Zuordnungen erhalten ebenfalls `NEWID()`. DELETE bleibt für beide Tabellen gesperrt. Der Access-Button **Heutiges Datum einfügen** entspricht `Text71.Value = Date` und setzt im Web `dateDialinDisabled` auf das heutige Datum.

`janus_connect` besitzt SELECT, INSERT und UPDATE auf `tblAnbindungDialin` sowie `tblAnbindungDialinEinwahlnummern`, jedoch kein DELETE. Für Dialins verwendet der Workspace-Manager die normale Standardbreite, aber eine erhöhte Fensterhöhe, damit die Access-nahe Classic-Maske einschließlich Einwahlnummern möglichst ohne internen Scrollbedarf nutzbar bleibt. Die Lookup-Quelle, welche `intEinwahlnummerID` in die sichtbare Rufnummer übersetzt, ist noch nicht vollständig identifiziert; deshalb wird nur die aus Access bestätigte Zuordnung ID 1 = 9598100 beschriftet und es werden keine weiteren Rufnummern geraten.

### 9.21 Domain-Einträge / DNS-Zonen

Das Access-Formular `frmDomainEintraege` basiert auf `AbfrageAllgemeineDomain`. Diese verbindet `tblAllgemeineDomain` abhängig von `strTyp` mit `tblDomains` oder `tblDomainAuftrag`. Das Unterformular verwendet direkt `tblDomainEintraege` und ist über `tblAllgemeineDomain.intID -> tblDomainEintraege.intIDAllgemeineDomain` verknüpft. Sichtbare Felder des Unterformulars sind `strName`, `strTyp` und `strAdresse`; `intTTL` wird von Access nicht sichtbar gepflegt.

Die Webpflege liegt unter `/domain-eintraege`. `tblAllgemeineDomain`, `tblDomains`, `tblDomainAuftrag` und `tblNameserver` werden nur gelesen. Für `tblDomainEintraege` besitzt `janus_connect` SELECT, INSERT, UPDATE und DELETE, weil Access das Löschen einzelner DNS-Einträge ausdrücklich unterstützt. Ganze Domains werden durch dieses Modul nicht gelöscht. Bei Neuanlage eines DNS-Eintrags wird grundsätzlich der bestätigte fachliche Standardwert `intTTL = 3600` verwendet.

Die Access-Logik markiert die Zone nach Speichern oder Löschen eines Eintrags als verändert und bietet beim Datensatzwechsel bzw. Schließen eine Aktualisierung der DNS-Zone an. Dabei wird unter anderem die SOA-Serial angepasst und anschließend ein Maschinenbefehl verschickt. Diese Nebenwirkung ist noch nicht in die Webanwendung übernommen; insbesondere werden keine im historischen VBA enthaltenen Zugangsdaten oder Maschinenkennwörter in Code, Git oder Dokumentation übernommen.

### 9.22 Domain eintragen

Das Access-Formular `frmAddDomain` legt zunächst einen Datensatz in `domains.dbo.tblDomains` an und erzeugt anschließend den zugehörigen Datensatz in `tblAllgemeineDomain` mit `strTyp = 'DOMAIN'`. Die Webfunktion liegt unter `/domain-eintragen`. Sie prüft Domainname und Kundennummer, verhindert doppelte Domainnamen und prüft die Kundennummer gegen `topsnetdb_safe.dbo.tblKunde`. Neue IDs werden über `OUTPUT INSERTED.intID` ermittelt; die historische Access-Verwendung von `SELECT Max(intID)` wird nicht übernommen.

Die von Access fest vorgegebenen Kontakt-, Nameserver- und Registry-Werte werden für diesen Migrationsschritt beibehalten. `intDNSSEC` wird explizit mit `0` geschrieben; DNSSEC-Funktionen sind nicht Bestandteil dieser Maske. Nach erfolgreicher Anlage erzeugt die Webanwendung in derselben SQL-Transaktion automatisch SOA, primären NS und sekundären NS in `tblDomainEintraege`. Für alle drei Einträge gilt der bestätigte Standard `intTTL = 3600`. Anschließend wird direkt die neue Zone in **Domain-Einträge bearbeiten** geöffnet. Datumswerte für `datRegistriertAm` und `tblDomainEintraege.Datum` werden serverseitig mit SQL Server `GETDATE()` gesetzt, damit keine sprach-/DATEFORMAT-abhängige String-Konvertierung zwischen PHP und SQL Server erfolgt.

Der historische Outlook-basierte Maschinenbefehl zum Neueinlesen der DNS-Zonen wird nicht übernommen. Insbesondere wird kein historisches hartcodiertes Maschinenkennwort in Anwendung, Dokumentation oder Repository migriert. Die spätere umfassende Domainverwaltung einschließlich Registry-/DNSSEC-Funktionen ist getrennt vorgesehen.

Die aktiven technischen Pflegebereiche Netze, Ports, Dialins, Domain-Einträge und Domain eintragen besitzen in Classic und Modern jeweils einen direkten Umschaltlink zur anderen Frontend-Darstellung. Damit ist der Wechsel nicht nur über das Hauptmenü möglich.



### 9.23 IPv4 Reverse

Die Access-Maske `frmDNSipv4ReverseEditor` arbeitet direkt auf `accountings.dbo.tblDNSipv4ReverseEditor` mit den Feldern `intID`, `intIPbyte1`, `intIPbyte2`, `intIPbyte3`, `intIPbyte4` und `intKundenID`. Die vier IP-Oktette werden im Web als vollständige IPv4-Adresse zusammengesetzt. Das Access-Formular besitzt keine Buttons und keine VBA-Ereignisse; eine bestätigte Speicherlogik liegt damit nicht vor.

Die Webfunktion `/ipv4-reverse` ist deshalb bewusst **read-only**. Sie bietet eine Suche nach Kundennummer sowie eine optionale IP-Suche. Der im Access-Formular gespeicherte Filter `intIPbyte3 = 185` wird dokumentiert, aber nicht global erzwungen, damit der tatsächliche Tabellenbestand vollständig einsehbar bleibt. `janus_connect` besitzt für diese Tabelle nur SELECT.

### 9.24 Domains zum Kunden zuordnen

Das Access-Formular zur Kundenzuordnung arbeitet auf `tblDomains` und zeigt nur Domains, deren `UMSTELLUNGintKundenID` noch `NULL` ist. Die Kundenliste basiert auf `tblKunde`; zusätzlich wird pro Kunde das Maximum von `tblAuftragPos.datErstelltAm` als jüngste Auftragsposition angezeigt.

Die Webfunktion `/domain-zu-kunden` trennt die Datenquellen entsprechend der heutigen SQL-Server-Datenbanken: Domains aus `domains`, Kunden aus `topsnetdb_safe`, Aufträge und Auftragspositionen aus `accountings`. Beim Zuordnen werden ausschließlich `tblDomains.datRegistriertAm` und `tblDomains.UMSTELLUNGintKundenID` geändert. Für `janus_connect` wurden deshalb nur spaltenbezogene UPDATE-Rechte auf diese beiden Felder vergeben. Das Registrierungsdatum wird mit `DATEFROMPARTS` gesetzt, damit keine sprachabhängige Datetime-Konvertierung entsteht.


### 9.25 Domains zu einer Auftragsposition zuordnen

Die Access-Maske „Zuordnung der Domains zu einer Auftragsposition“ ermittelt Domains mit gesetzter `UMSTELLUNGintKundenID`, für die noch keine Zeile in `tblAnbindungen` mit `intTyp = 6` und `intAnbindungReferenz = tblDomains.intID` existiert. Nach Auswahl einer Domain werden alle Aufträge des Kunden sowie vorhandene Auftragspositionen mit `intStaffelTyp = 5` angeboten.

Die Webfunktion `/domain-zu-auftragsposition` bildet diesen Kernworkflow nach. Beim Zuordnen wird in `accountings.dbo.tblAnbindungen` eine Domain-Accounting-Anbindung angelegt: `intTyp = 6`, `intAnbindungReferenz = Domain-ID`, `boolAbrechenbar = 1`, `dateAbrechenbarStart = tblDomains.datRegistriertAm`, `dateAbrechenbarEnde = 31.12.2029`, `strKopieRechnungsinfo = Domainname` und `intAuftragsPos = ausgewählte Position`. Zusätzlich wird ein neuer `rowguid` erzeugt. Vor dem Insert wird serverseitig geprüft, dass Domain und Position zum selben Kunden gehören, die Position `intStaffelTyp = 5` hat und noch keine Domain-Anbindung existiert.

Der produktiv bestätigte Access-Ablauf ist: **Alle** deaktivieren, Kundennummer eingeben, Domain auswählen, die gewünschte Domain-Auftragsposition markieren und **Anbindung Neu** ausführen. Die Schreibaktion wird genau einmal bestätigt. In der Webversion erfolgt die Kundeneingrenzung direkt über das Feld **Kundennr.**; vor dem Insert erscheint ebenfalls genau eine Bestätigungsabfrage.

Die Access-Funktion `Neue Konditionsrabatte` aus `frmDomainKonditionenRabatte` ist migriert. Die Tabelle `domains.dbo.tblDomainKonditionenRabatte` enthält `intAuftragsPosID`, `fRabattEinrichtung` und `fRabattRegulaer`. Für eine Auftragsposition existiert fachlich höchstens ein Rabattsatz: vorhandene Werte werden aktualisiert, andernfalls wird ein neuer Datensatz angelegt. Die Webvalidierung korrigiert den historischen VBA-Fehler (`< 0 And > 100`) und erlaubt für beide Prozentwerte ausschließlich 0 bis 100. DELETE wird nicht benötigt.

`Lookup starten` bleibt gemäß Migrationsentscheidung deaktiviert.

### 9.26 Nicht migrierte Alt-Funktionen im Domain-Menü

Die Access-Funktionen **Handles pflegen**, **Owner pflegen**, **Look up starten** und **Aktuelle Domain-Aufträge** werden im Web-Frontend bewusst deaktiviert belassen. Nach Auskunft aus dem produktiven Arbeitsablauf wurden diese Funktionen nicht genutzt. Eine eigenständige Nachmigration würde daher nur Altlast ohne praktischen Nutzen erzeugen.

Falls entsprechende Aufgaben künftig wieder benötigt werden, sollen sie nicht auf Basis der alten Access-Logik neu gebaut werden, sondern im Rahmen der später geplanten zentralen Domainverwaltung über die DENIC-API fachlich neu eingeordnet und umgesetzt werden. Das betrifft ausdrücklich auch **Aktuelle Domain-Aufträge**.

### 9.27 Rechnungstool

Das bisherige Rechnungstool liegt als VB.NET-WinForms-Anwendung unter `/home/saschaw/rt` und verwendet .NET Framework 4.7.2. Die Anwendung besteht aus mehreren Komponenten für Fakturierung, Datenbankzugriff, SEPA, Word/PDF, Mahnwesen, Zeit-/Intervallberechnung und verschiedene Accounting-Adapter. Zentrale Datenquellen sind `accountings`, `topsnetdb_safe` und `domains`; damit baut die geplante Webmigration auf denselben SQL-Server-Datenbanken auf, die bereits von der Webanwendung verwendet werden.

Die Migration erfolgt nicht als direkte 1:1-Portierung des monolithischen VB-Codes. Die Fakturierungslogik wird in getrennte Web-Services für Rechnungslauf, Positionsberechnung, Accounting, Domainabrechnung, Rechnungsnummern, Dokumenterzeugung und später SEPA/XRechnung aufgeteilt. Der erste Schritt ist ausdrücklich ein rein lesender Vorschau-/Testlauf, damit die Webberechnung mit dem bestehenden Tool verglichen werden kann, bevor produktive Rechnungsdaten geschrieben werden. Der geschützte Einstiegspunkt `/fakturierung` ist dafür eingerichtet. Der Hauptmenüpunkt **Rechnungstool starten** ist für berechtigte Benutzer aktiviert; für andere Benutzer bleibt er deaktiviert.

Phase 1 enthält jetzt die erste echte Datenvorschau. Die Auftragsliste übernimmt die drei im Alttool sichtbaren Auswahlarten **Nachträglich abrechnen**, **Im Voraus abrechnen** und **Domainaufträge**. Der Zeitraum wird wie beim bisherigen Rechnungslauf berücksichtigt; als Vorgabe wird der komplette Vormonat verwendet, entsprechend dem zuletzt im Alttool beim Öffnen gesetzten Zeitraum. Fakturierbeginn muss vor bzw. innerhalb des Endes liegen, ein vorhandenes Stornodatum muss nach dem Beginn des gewählten Zeitraums liegen. Die Auswahl nutzt außerdem die bestätigten Flags `boolRechnungstool`, `boolSponsoring`, `boolVoraus` und `boolDomainrechnung` sowie eine vorhandene Rechnungsanschrift. Pro Aufruf werden höchstens 500 Treffer angezeigt.

Die Bedienoberflächen des Rechnungstools unterscheiden sich bewusst: **Classic** übernimmt die vertraute Struktur des bisherigen WinForms-Tools mit den Registerbereichen Datumsbereich, Test/Monatslauf, Auftrags/Kundennummer und Logging. Funktionen, deren Berechnungs- oder Schreiblogik noch nicht migriert ist, bleiben dort deaktiviert. **Modern** verwendet stattdessen eine webtypische Arbeitsansicht mit kompakter Filterleiste, scrollbarer Auftragsliste links und dauerhaft sichtbarer Detailansicht rechts. Damit ist für die moderne Oberfläche kein Nachbau historischer Bedienmuster erzwungen.

Das Classic-Frontend blendet die Auftragsliste nicht dauerhaft unter der Rechnungslauf-Maske ein und bietet stattdessen einen direkten Wechsel auf die moderne Ansicht. Die moderne Darstellung behält die Auftragsliste als Arbeitsbereich mit Detailansicht.

Für einen ausgewählten Auftrag zeigt die Vorschau die Auftragspositionen und enthält nun die erste echte Berechnungsengine für normale Festpreispositionen (`intStaffelTyp = 0`). Die Berechnung orientiert sich an `FindeBerechnungsTage`, `BerechneDaten`, `FindeBerechnungsArt`, `FindeMengeUndPreis` und `IstBereitsGenauGleichBerechnet` des VB.NET-Alttools. Berechnungstermine werden aus `BETAtblAbrechnungsArt.intEinheiten` und `strDimension` (`TAG`, `MONAT`, `JAHR`, `EINMALIG`) erzeugt; Monatsintervalle bleiben am ursprünglichen Kalendertag verankert und werden bei zu kurzen Monaten wie `KorrigiereTag` auf den letzten gültigen Tag korrigiert. `datFakturierBis` und `tblAuftrag.datStorniereAb` begrenzen den Zeitraum.

Für Festpreispositionen entspricht der Basisbetrag dem Altcode `Round(intMenge * fEndpreis, 2)`. Positionsrabatte werden anschließend als eigener negativer Betrag berücksichtigt. Die Umsatzsteuer wird wie in `NotiereSteuer` je Betrag mit acht Nachkommastellen gesammelt und erst für die Gesamtsumme auf zwei Stellen gerundet. `tblAuftragPosBerechnet` wird pro Position und Berechnungstermin geprüft. Ein identischer historischer Betrag gilt als bereits berechnet und wird nicht erneut summiert; eine Preisabweichung wird als Konflikt markiert, weil das Alttool den Auftrag in diesem Fall abbricht. Eingefrorene Aufträge werden ebenfalls nicht als abrechenbar gewertet. Für Staffeltyp 1 wird bei aktivierter Option **Accountings berücksichtigen** wie im Alttool die Summe aus `tblAnbindungAuswertung.decGesamt` für Monat/Jahr und abrechenbare Anbindungen ermittelt und die erste Preisstufe aus `tblStaffelpreise` gewählt, deren `intMenge` die Nutzung abdeckt. Staffeltyp 2 wertet `tblLinearStaffel` aus: Freimenge, Preis je Einheit, Basispreis und Abrechnungseinheit werden mit dem Monats-Accounting kombiniert. Die Alttool-Logik der ganzzahligen `CInt`-Zwischenschritte wird nachgebildet. Bei deaktivierter Accounting-Option werden Positionen mit `intStaffelTyp > 0` wie im Alttool übersprungen. Staffeltyp 3 (Zeittarif) bildet zusätzlich die historische Dialin-EVN-Bewertung nach: vorhandene `fNettoPreis`-Werte werden übernommen, andernfalls wird bei Zeittarif-Anbindungen anhand von Freisekunden, Mindestabnahme, Taktung und den Zeitfenstern aus `tblZeittarifeZonen` rein lesend neu bewertet. Die im Alttool vorhandene Cache-Aktualisierung von `tblAnbindungenDialinWerte.fNettoPreis` wird im Web-Testlauf bewusst nicht ausgeführt. Staffeltyp 4 bewertet die größere von Ein- und Ausgangsmenge, Staffeltyp 7 deren Summe; beide rechnen den Monatswert wie das Alttool in kBit/s um und wählen die erste passende Stufe aus `tblBandbreiteStaffelPreise`. Staffeltyp 5 bildet nun auch die Domainabrechnung lesend nach. Dazu werden die Domainkondition der Position, optionale Rabatte, aktive Domain-Anbindungen und das Registrierungsdatum ausgewertet. Monats- und Jahresintervalle werden wie in `tnTerminBerechnung` aus dem Registrierungsdatum fortgeschrieben; eine besondere Anfangsphase wird über `intEnthaltenAnzahl`/`intEnthaltenEinheit` bestimmt. Wie im Alttool wird die Anfangsphase über die Einrichtungsgebühr bepreist und verhindert im betreffenden Monat die zusätzliche reguläre Berechnung. Fehlende aktive Domain-Referenzen werden als Konsistenzfehler ausgewiesen statt mit 0 € weitergerechnet. Staffeltyp 6 verwendet die Monatsnutzung und den passenden Bereich aus `tblBereichsStaffelPreise`; Nettopreis ist `Grundgebühr + BereichsGrundgebühr + Stückpreis × (Nutzung - MengeAb)`. Zusätzlich bildet die Vorschau nun die historische Vorberechnung/Nachberechnung nach. Solange `datVorberechnenBis` den Berechnungstermin umfasst, wird der dargestellte Rechnungszeitraum wie im Alttool um einen Monat vorgezogen. Für Staffeltyp 1 wird `tblAccountingKonto` des aktuellen Rechnungsmonats gelesen, die bereits vorausbezahlte Staffel mit der tatsächlich erreichten Staffel verglichen und bei einem Staffelwechsel die Erstattung der alten Staffel plus die Nachberechnung der aktuellen Staffel als zusätzlicher Nettobetrag ausgewiesen. Der für den Folgemonat vorgesehene Accounting-Stand wird nur angezeigt; die historischen `DELETE`/`INSERT`-Schreibzugriffe aus `AktualisiereAccountingKonto` werden im Web-Testlauf bewusst nicht ausgeführt. Endet die Vorberechnung, wird keine neue Vorauszahlung mehr angesetzt; eine zuvor ermittelte Staffelkorrektur bleibt entsprechend dem Altcode bestehen. Es werden weiterhin keine Fakturierungsdaten geschrieben.

Für einen ausgewählten Auftrag wird nun zusätzlich ein **kompletter read-only Auftragstestlauf** aufgebaut. Dieser fasst nur neu abzurechnende Berechnungstermine zu einer Rechnungsansicht zusammen, prüft Rechnungsanschrift, Zahlungsbedingung, DATEV-Kundenkonto und DATEV-Produktkontierung und kennzeichnet Konflikte oder Accountingfehler als blockierend. Bereits berechnete Termine werden nicht erneut in die Testrechnung aufgenommen. Die Testrechnung zeigt Rechnungsdatum, Fälligkeit, Versandart, Zahlungsbedingung, Netto-/Steuer-/Bruttosummen und Skontostufen. Rabattzeilen sowie Erstattungs-/Nachberechnungszeilen aus der Staffel-Vorberechnung werden getrennt dargestellt, damit die Vorschau näher an den Zeilen des Alttools liegt. `tblZahlungsbedingung` wird entsprechend dem VB-Code aus `accountings` gelesen. Bankdaten werden in der Oberfläche nur maskiert angezeigt. Der Testlauf vergibt keine Rechnungsnummer und schreibt weder `tblRechnung` noch `tblAuftragPosBerechnet`, `tblAccountingKonto` oder Dokumentdateien.

Für den nächsten Paritätsschritt ist außerdem ein read-only Kunden- und Gesamttestlauf umgesetzt. Der Kundenlauf nutzt die im Formular angegebene Kundennummer und verarbeitet alle passenden Aufträge innerhalb des gewählten Zeitraums und Abrechnungstyps. Der Gesamtlauf ignoriert Auftrag/Kunde/Suchtext und verarbeitet die vollständige Kandidatenmenge des gewählten Zeitraums und Abrechnungstyps. Jeder Auftrag wird über dieselbe Positionsberechnung und denselben `InvoiceOrderTestRunService` wie die Einzelvorschau geprüft. Fehler oder Inkonsistenzen eines einzelnen Auftrags werden als `blocked` protokolliert und stoppen den restlichen Lauf nicht. Summen für Netto, Steuer und Brutto enthalten ausschließlich Aufträge mit Status `ready`; `nothing_to_invoice` und `blocked` werden separat gezählt. Die frühere statische Box **Manuell prüfen** wurde am 18.09.2026 durch eine tägliche Konsistenzprüfung ersetzt. Ein eigener Laravel-Scheduler-Container führt jeden Morgen um 08:00 Uhr in der Zeitzone `Europe/Berlin` einen rein lesenden Gesamttest für Nachberechnung, Vorausberechnung und Domainabrechnung über den letzten vollständig abgeschlossenen Monat aus. Zusätzlich werden aktive Aufträge auf fehlende oder einer anderen Kundennummer zugeordnete Rechnungsanschriften geprüft. Das Ergebnis wird mit Prüfzeitpunkt und Abrechnungszeitraum im persistenten Anwendungscache gespeichert und unterhalb des Rechnungstools angezeigt. Die Box enthält direkte Links auf betroffene Aufträge, nimmt keine Datenänderungen vor und bleibt bei einem späteren Seitenaufruf schnell, weil der Gesamttest nicht während des Webrequests ausgeführt wird. Der Lauf bleibt vollständig lesend und erzeugt weder Rechnungsnummern noch Datenbank- oder Dateischreibzugriffe. Seit der vollständigen Stored-Procedure-Auswertung übernimmt die Prüfung außerdem die aktiven fachlichen Kontrollen aus `spCheckAufAbrechnungsStart` und `spCheckAufAktuelleAccountings`: Aktualität von Switch-Accounting, HERMES-IP-Accounting und Monatssummen sowie Soll-/Ist-Abweichungen und Aktualisierungsalter aktiver Portbeschreibungen. Die Webapp führt die alten Procedures bewusst nicht aus, weil diese selbst E-Mails versenden und temporäre Tabellen anlegen. Nicht mehr aktive, im SQL-Code auskommentierte Dial-in-, SMS-, STHS3- und BONN9-Benachrichtigungen werden nicht wieder aktiviert. Fehlende Leserechte oder einzelne Abfragefehler werden als Systemproblem im Bericht gespeichert und stoppen die übrigen Prüfungen nicht. Die dafür erforderlichen SELECT-Rechte auf `tblAccountingIntervall`, `tblAccountingNetzeTageswerte`, `tblAnbindungAuswertung` und `tblPort` wurden am 18.09.2026 für `janus_connect` vergeben und durch einen vollständigen Prüflauf bestätigt. Die exakten GRANT-Anweisungen, Grenzwerte und der verifizierte Prüfstand stehen im `SQL_WIKI.md`.

Die Rechnungsnummernlogik wurde anhand des Bestands von 1999 bis 2026 und anschließend direkt in `komponenteFakturierungswesen.dll` analysiert. Seit 2000 folgt sie dem Format `JJJJ` plus sechsstelliger Jahreszähler; für 2026 reicht die gespeicherte Folge aktuell bis `2026001462`. Entgegen der ersten, nur auf Stored Procedures und Datenbankobjekte gestützten Einschätzung verwaltet das Alttool den Jahreszähler in `tblRechnungsNummern` (`intRechnungsJahr`, `intLfdNr`) und setzt ihn ausdrücklich innerhalb seiner Datenbanktransaktion. Die Webapp liest diesen Zähler für die Simulation; fehlt das SELECT-Recht, fällt sie sichtbar gekennzeichnet auf `MAX(tblRechnung.intRechNr)` zurück.

Die produktive Schreibstrecke ist am 18.09.2026 technisch vorbereitet worden. `InvoiceWriteService` sperrt Auftrag und Zählerzeile mit `UPDLOCK, HOLDLOCK`, berechnet den Auftrag innerhalb der Transaktion erneut und schreibt Zähler, Rechnung, berechnete Positionszeilen und gegebenenfalls das Accountingkonto atomar. Zähler-/Bestandsabweichungen, nicht mehr fakturierbare Aufträge und SQL-Fehler lösen einen vollständigen Rollback aus. Die Oberfläche zeigt SQL-Rechtestatus und Serverfreigabe; ein Commit erfordert den exakten Bestätigungstext und wird mit AD-Benutzer protokolliert. Die benötigten objektgenauen SQL-Rechte wurden am 18.09.2026 vergeben. Ein kontrollierter Commit erzeugte auf dem Testserver für Auftrag `4316` die Rechnung `2026001463` mit drei Positionszeilen und 11,78 € brutto; Zähler, Summen und anschließender Paritätsvergleich waren vollständig konsistent. Ein zuvor ausgelöster Fehler wurde nachweislich einschließlich Zähleränderung zurückgerollt. Die Funktion bleibt durch `INVOICE_WRITES_ENABLED=false` deaktiviert, bis die fachliche Freigabe für die Bedienoberfläche erfolgt.

Der historische Paritätsvergleich ist ebenfalls vollständig read-only. Nach Eingabe einer Rechnungsnummer oder internen Rechnungs-ID werden die über `tblAuftragPosBerechnet.intRechnungIntID` gespeicherten Positions- und Berechnungstermine geladen und mit der heutigen Weblogik erneut bewertet. Angezeigt werden Netto, Steuer und Brutto sowie Positionen und Rabatte jeweils historisch, neu berechnet und als Differenz. Fehlende oder zusätzliche Zeilen werden als Abweichung markiert. Damit lassen sich Änderungen an Preisen, Stammdaten oder Accountingwerten nachvollziehen, bevor produktive Schreiblogik freigeschaltet wird. Zusätzlich ermittelt ein systematischer Stichprobenlauf automatisch je eine möglichst aktuelle, unterschiedliche Altrechnung für Festpreis, Rabatt, mehrere Positionen, Accounting/Staffel, Domain und Vorausberechnung. Der verifizierte Lauf vom 18.09.2026 nutzte `2026001462`, `2026001437`, `2026001456`, `2026001425`, `2026001461` und `2026001460`; alle sechs Kategorien stimmten cent- und zeilengleich mit der Weblogik überein.

Fachlich dient diese Funktion vor allem als Abnahme- und Sicherheitsnachweis für die Ablösung des Alttools: Repräsentative Festpreis-, Rabatt-, Mehrpositions-, Staffel-/Accounting-, Domain- und Vorausberechnungsrechnungen werden vor dem Produktivstart erneut berechnet und auf centgenaue Übereinstimmung geprüft. Nach der Einführung bleibt der Vergleich als Diagnosewerkzeug für Reklamationen, Sonderfälle und Regressionstests nach Änderungen an Preisen oder Berechnungslogik sinnvoll. Er ist keine notwendige Funktion für den normalen täglichen Rechnungslauf und kann nach abgeschlossener Migration in einen Admin-/Diagnosebereich verlagert werden.

Die Kundendatenquellen des Alttools wurden am 18.09.2026 anhand des VB.NET-Codes und der aktuellen Datenbestände verifiziert. Der Auftrag stammt aus `accountings.dbo.tblAuftrag`; der zugehörige Kunde einschließlich DATEV-Kundenkonto wird aus `topsnetdb_safe.dbo.tblKunde` gelesen. Die konkrete Rechnungsanschrift, E-Mail-Adresse und Bankverbindung stammen dagegen über `tblAuftrag.intAnschriftID` aus `accountings.dbo.tblRechnungsanschrift`. Auftrag 5772 und Kunde 6384 sind nach dem Datenbank-Refresh konsistent und gehören zur Indicom GmbH. Unter den 227 im Rechnungstool verwendeten Kundennummern fehlt aktuell kein Datensatz in `topsnetdb_safe`. Zwei historische, bereits stornierte Aufträge (5548 und 5578) verweisen jedoch auf eine Rechnungsanschrift einer anderen Kundennummer. Da das Alttool `tblAuftrag.intKID = tblRechnungsanschrift.intKID` verlangt, bildet die Webauswahl diese Bedingung nun ebenfalls ab; der Einzeltestlauf blockiert eine abweichende Zuordnung zusätzlich defensiv.

Nach dem Datenbank-Refresh wurden auf dem SQL-Server-2019-Teststand außerdem gezielte Performance-Indizes für die tatsächlich verwendeten Rechnungstool-Abfragen ergänzt. Die Auswahl orientiert sich an den wiederkehrenden Zugriffsmustern auf berechnete Positionen, Monats-Accounting, Vor-/Nachberechnung, Staffelpreise, Anbindungen und Domain-Rabatte. Bestehende Altindizes wurden bewusst nicht entfernt. Die exakten Indexnamen, Key-/INCLUDE-Spalten, der fachlich-technische Grund je Index sowie die gemessenen Vorher-/Nachher-Laufzeiten sind im `SQL_WIKI.md` unter **Testserver: ergänzende Performance-Indizes** dokumentiert.

Die Fakturierungsoberfläche ist als moderne responsive Karten-/Tabellenansicht umgesetzt. Nach Klick auf **Vorschau** wird der ausgewählte Auftrag oberhalb der Trefferliste gerendert und über einen URL-Fragmentanker direkt angesprungen. Ein Rücksprung **Zur Auftragsliste** führt wieder zur Trefferliste. Damit bleibt die Auswahl auch bei langen Auftragslisten unmittelbar bedienbar, ohne manuelles Scrollen bis zum Seitenende.

Das historische Tool erzeugt Dokumente über Microsoft-Word-COM und verwendet lokale Outlook-Integration. Diese Windows-spezifischen Komponenten werden auf dem Linux-Webserver nicht übernommen. Die aktive Vorlage `/mnt/midas-bh/Rechnungswesen/vb/templates/Rechnung-4.dot` und die letzte Papierrechnung `2026001462.doc/.pdf` wurden deshalb unverändert und schreibgeschützt nach `/srv/dbapp/reference/midas-invoice-templates/originals` beziehungsweise `samples` kopiert; Prüfsummen liegen in `SHA256SUMS`. Eine getrennte Arbeitskopie befindet sich unter `working/Rechnung-4.dot`, die MIDAS-Originalfreigabe bleibt unverändert read-only.

Die Vorschau erzeugt weiterhin ein nicht gespeichertes A4-PDF mit Wasserzeichen. Für verbindlich geschriebene Rechnungen ist nun eine getrennte Ablage `/mnt/midas-invoices` eingebunden. Das aktive Adminprofil `storage.midas_invoices` verwaltet lokalen Mount, UNC-Wurzel und Dateinamenschema. Standard ist `{year}/Rechnungen/Papier/{invoice_number}.pdf`; daraus entsteht beispielsweise `\\janus\midas-invoices\2026\Rechnungen\Papier\2026001464.pdf`. Der Admin-Schreibtest erzeugt eine zufällige Testdatei, liest sie zurück und entfernt sie sofort. Erst ein aktives, erfolgreich getestetes Read-write-Profil gilt als bereit.

Der Auftragstestlauf erzeugt zusätzlich einen Versand- und Zahlungsplan. Er prüft Papier-/E-Mail-Kennzeichen, E-Mail-Adresse, Zahlungsbedingung, SEPA-Freigabe, Bankdaten, Einzugsbetrag und Sequenztyp `FRST`, `RCUR`, `FNAL` oder `OOFF`. Negative Rechnungen werden als **Storno/Teilstorno**, Nullbeträge als **Nullrechnung** bezeichnet. Anders als der Altcode erzwingt die Webapp SEPA nicht bei fehlender Freigabe und setzt beim bloßen Erzeugen kein Versanddatum. E-Mail-Versand, Druckübergabe und SEPA-Dateierzeugung bleiben bis zur getrennten Ausführung und Freigabe ohne Außenwirkung.

Beim Rechnungsschreiben wird das endgültige PDF nach der gesperrten Nummernvergabe, aber innerhalb des kontrollierten Commit-Ablaufs erzeugt. Der UNC-Pfad wird direkt in `tblRechnung.strPfadZurRechnung` gespeichert. Scheitert anschließend die SQL-Transaktion, entfernt die Webapp das bereits erzeugte PDF wieder; ein fehlgeschlagenes Aufräumen wird als eigener Fehler protokolliert. Alte Rechnungen werden weiterhin über den read-only MIDAS-Mount geöffnet, neue PDFs über das getrennte Profil. Der kontrollierte Test `2026001464` bestätigte PDF-Datei, Datenbankpfad, Webauslieferung, drei Positionszeilen, Zählerstand und cent-/zeilengleichen Paritätsvergleich. E-Mail-, SEPA- und XRechnungs-Funktionen bleiben separate Migrationsschritte.

Für das Rechnungstool wird ein eigenes Benutzerhandbuch in `docs/RECHNUNGSTOOL_HANDBUCH.md` geführt. Technische Informationen bleiben Bestandteil dieser Datei. Die verwendeten und bestätigten SQL-Abfragen sowie Berechtigungs-Statements werden weiterhin zentral in `docs/SQL_WIKI.md` gesammelt, damit keine zweite konkurrierende SQL-Dokumentation entsteht.


Der bestehende SQL-Server-Wartungsplan `cleanup_alte_accountingdaten` bleibt als Infrastrukturaufgabe erhalten. Er entfernt Accounting-Roh-/Zwischendaten aus `tblAccountingFromPort`, `tblAccountingNetzeTageswerte` und `tblAccountingIntervall`, die älter als zwei Jahre sind; diese Tabellen werden von der Laravel-Webapp derzeit nicht verwendet. Die bisherige Jobfassung zählt gelöschte Datensätze fehlerhaft, weil `@@ROWCOUNT` erst nach allen drei DELETEs ausgewertet wird. Die korrigierte Fassung und die empfohlenen Sicherheitsmaßnahmen sind im `SQL_WIKI.md` dokumentiert.

## 9.28 Produktivmigration SQL Server: Minerva → Cardea

Dieser Abschnitt ist die verbindliche Cutover-Checkliste für den endgültigen Umzug der produktiven Datenbanken `accountings`, `domains` und `topsnetdb_safe` vom Altsystem **Minerva** auf den SQL-Server-2019-Zielserver **Cardea**. Die Migration erfolgt in einem angekündigten Wartungsfenster. Sobald die finale Sicherung beginnt, müssen alle schreibenden Alt- und Webanwendungen gestoppt beziehungsweise gesperrt sein. Minerva darf bis zur Freigabe nicht mehr beschrieben werden.

### Vorbereitung und Referenzwerte

1. Zuständigkeiten, Wartungsfenster, Abbruchzeitpunkt und Kommunikationsweg festlegen.
2. Auf Cardea ausreichend freien Speicher in den vorgesehenen Verzeichnissen `E:\Datenbanken\accountings`, `E:\Datenbanken\domains` und `E:\Datenbanken\topsnetdb_safe` bestätigen.
3. Vor dem Cutover auf Minerva Datenbankstatus, Größen, Kompatibilitätslevel, logische Dateinamen, Benutzer sowie Referenzzählungen erfassen. Die unmittelbar vor dem Umzug ermittelten Werte werden im Migrationsprotokoll gespeichert; ältere Beispielwerte sind keine Abnahmebasis.
4. Sicherstellen, dass die für Cardea vorgesehenen Logins vorhanden sind. Datenbankbenutzer werden nach dem Restore den passenden Server-Logins zugeordnet; insbesondere ist `janus_connect` in allen drei Datenbanken zu prüfen.
5. Veeam-Sicherungsstatus und Rückfallmöglichkeit prüfen. Veeam bleibt das reguläre Sicherungssystem; der Datenbanktransfer für den Cutover erfolgt dennoch mit einer konsistenten finalen SQL-Server-Sicherung.

### Cutover

1. Schreibende Dienste, Access-Frontends, Rechnungstool und Laravel-Schreibfunktionen stoppen oder in Wartung setzen.
2. Aktive Sitzungen und offene Transaktionen auf Minerva kontrollieren. Erst danach die finale Vollsicherung der drei Datenbanken erstellen.
3. Sicherungsdateien nach Cardea übertragen und Dateigrößen beziehungsweise Prüfsummen protokollieren.
4. Datenbanken auf Cardea mit `WITH MOVE` in die vorgesehenen Daten- und Logverzeichnisse wiederherstellen. Bestehende Testkopien dürfen erst nach eindeutiger Identifikation und gesicherter Rückfallmöglichkeit ersetzt werden.
5. Datenbanken online schalten, Owner und Compatibility Level prüfen und anschließend Benutzer-/Login-Mappings reparieren.
6. `DBCC CHECKDB` für alle drei Datenbanken ausführen. Der Cutover wird bei Konsistenzfehlern nicht freigegeben.
7. Referenzzählungen und Kontrollsummen mit Minerva vergleichen. Abweichungen müssen vor der Umschaltung erklärt und dokumentiert sein.
8. Die gezielt dokumentierten Nonclustered-Indizes auf Cardea prüfen und nur fehlende Indizes nach dem in `SQL_WIKI.md` festgehaltenen Stand ergänzen.
9. Verbindungen der Webapp und aller noch benötigten Altanwendungen auf Cardea umstellen. Danach technische Smoke-Tests und fachliche Anwendungstests durchführen.
10. Erst nach erfolgreicher Abnahme Schreibzugriffe freigeben und Minerva read-only beziehungsweise abgeschaltet belassen.

### Abnahme

Mindestens zu prüfen sind Anmeldung/SSO, Kunden- und Auftragssuche, Ansprechpartner, Projekte, Rechnungsansicht, DATEV-Auswertungen, Domainfunktionen, Accounting-Auswertungen und der read-only Rechnungstool-Gesamttestlauf. Zusätzlich werden Datenbankname und Serverziel aus jeder Anwendung kontrolliert, damit kein Client unbemerkt weiter Minerva verwendet. Ergebnis, Uhrzeit, Prüfer und mögliche Abweichungen werden im Migrationsprotokoll festgehalten.

### Rollback

Ein Rollback erfolgt, wenn Restore, Konsistenzprüfung, Referenzvergleich oder ein kritischer Anwendungstest fehlschlägt. Solange Cardea noch nicht für Schreibzugriffe freigegeben wurde, werden die Verbindungen auf Minerva zurückgestellt und die dortigen Anwendungen wieder gestartet. Wurde Cardea bereits beschreibbar freigegeben, darf nicht einfach auf Minerva zurückgeschaltet werden: Zuerst müssen die seit der Freigabe auf Cardea entstandenen Änderungen gesichert und fachlich bewertet werden. Deshalb bleibt die Schreibfreigabe der letzte Cutover-Schritt.

Die zugehörigen ausführbaren Vor-/Nachprüfungen, Benutzer-Mappings und CHECKDB-Statements stehen zentral im Abschnitt **Produktivmigration Minerva → Cardea** des `SQL_WIKI.md`.

## 10. Dokumentationspflege

Die vier Dokumentationsziele werden im Classic-Frontend über eine linke Direktleiste und im Modern-Frontend über Direktbuttons in der Kopfleiste angeboten. Die Links verwenden `target="_blank"` mit `rel="noopener"` und öffnen daher bewusst einen neuen Browser-Tab statt eines internen Workspace-Fensters.

Diese Datei ist die technische Quelle für die spätere Projektdokumentation. Bei Änderungen an Architektur, Tabellen, Beziehungen, Rechten, Geschäftslogik oder Modulen muss sie zusammen mit dem Code aktualisiert werden.

Das allgemeine Benutzerhandbuch wird parallel in `docs/HANDBUCH.md` gepflegt. Für das Rechnungstool existiert zusätzlich `docs/RECHNUNGSTOOL_HANDBUCH.md`. `docs/SQL_WIKI.md` bleibt das gemeinsame SQL-Statement-Wiki für die gesamte Webanwendung einschließlich Rechnungstool. Dort werden bestätigte Access-Abfragen, fachlich relevante direkte SQL-Abfragen und Berechtigungs-Statements mit kurzer Erklärung gesammelt. Die vier Markdown-Dateien werden über feste Links in der Webanwendung angezeigt und können nach Abschluss der Migration als Word- oder PDF-Dokument ausgegeben werden. Die Dokumentationsansichten einschließlich SQL-Wiki besitzen in Classic und Modern eine Volltext-Suche mit Trefferanzahl sowie Vor-/Zurück-Navigation zwischen Fundstellen. Abschnitt 10 bleibt bewusst der letzte Hauptabschnitt dieser Datei; neue technische Themen werden davor eingeordnet.

Der SQL-Server-2019-Teststand wurde am 17.09.2026 nach dem Refresh zusätzlich mit gezielten, nicht destruktiven Performance-Indizes für die Rechnungstool-/Webapp-Zugriffspfade versehen. Es wurden ausschließlich neue Nonclustered-Indizes ergänzt; bestehende historische Indizes wurden bewusst nicht entfernt. Der August-2026-Gesamttestlauf blieb fachlich identisch und zeigte nur eine moderate Laufzeitverbesserung auf rund 3,1 s, was zur aktuell noch stark anwendungsseitig geprägten Verarbeitung passt.
