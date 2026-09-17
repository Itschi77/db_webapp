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

Für die serverseitige AD-Gruppenautorisierung läuft der unter `ops/ad-authz` abgelegte lokale Helper als Systemdienst auf `127.0.0.1:8090`. Er übernimmt den bereits per Kerberos authentifizierten Principal aus `X-Remote-User` und prüft die tatsächliche SSSD-Gruppenmitgliedschaft über die lokale NSS-Auflösung. Freigegeben sind ausschließlich die Gruppen `DB-Webapp-Users` und `DB-Webapp-Rechnungstool`; beliebige AD-Gruppen können über den Helper nicht abgefragt werden. Der Helper kann mehrere freigegebene Gruppen in einer Anfrage prüfen; alle angeforderten Gruppen müssen vorhanden sein. Nginx bindet die Prüfung über `auth_request` ein. Für die gesamte Webanwendung wird `DB-Webapp-Users` erzwungen. Der Pfad `/fakturierung` wird zusätzlich über eine zweite Authz-Subrequest auf `DB-Webapp-Rechnungstool` geschützt, sodass dort beide Gruppen erforderlich sind. Ohne Kerberos-Ticket erfolgt HTTP 401, ohne benötigte Gruppenmitgliedschaft HTTP 403.

Nginx reicht den bestätigten AD-Principal als `X-Remote-User` an Laravel weiter. Zusätzlich liefert der Authz-Helper bei vorhandener Rechnungstool-Mitgliedschaft den internen Header `X-DB-Webapp-Rechnungstool: 1`; Nginx setzt diesen Header kontrolliert für Laravel. Die Anwendung übernimmt daraus den angemeldeten Kurznamen und die Rechnungstool-Berechtigung. Der Laravel-Middleware-Schutz `invoice.access` verweigert `/fakturierung` ebenfalls mit HTTP 403, wenn der interne Berechtigungsheader fehlt. Damit wird der Menüstatus nicht als Sicherheitsgrenze verwendet, sondern nur als Bedienhinweis.

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

Für Festpreispositionen entspricht der Basisbetrag dem Altcode `Round(intMenge * fEndpreis, 2)`. Positionsrabatte werden anschließend als eigener negativer Betrag berücksichtigt. Die Umsatzsteuer wird wie in `NotiereSteuer` je Betrag mit acht Nachkommastellen gesammelt und erst für die Gesamtsumme auf zwei Stellen gerundet. `tblAuftragPosBerechnet` wird pro Position und Berechnungstermin geprüft. Ein identischer historischer Betrag gilt als bereits berechnet und wird nicht erneut summiert; eine Preisabweichung wird als Konflikt markiert, weil das Alttool den Auftrag in diesem Fall abbricht. Eingefrorene Aufträge werden ebenfalls nicht als abrechenbar gewertet. Für Staffeltyp 1 wird bei aktivierter Option **Accountings berücksichtigen** wie im Alttool die Summe aus `tblAnbindungAuswertung.decGesamt` für Monat/Jahr und abrechenbare Anbindungen ermittelt und die erste Preisstufe aus `tblStaffelpreise` gewählt, deren `intMenge` die Nutzung abdeckt. Staffeltyp 2 wertet `tblLinearStaffel` aus: Freimenge, Preis je Einheit, Basispreis und Abrechnungseinheit werden mit dem Monats-Accounting kombiniert. Die Alttool-Logik der ganzzahligen `CInt`-Zwischenschritte wird nachgebildet. Bei deaktivierter Accounting-Option werden Positionen mit `intStaffelTyp > 0` wie im Alttool übersprungen. Staffeltypen 3 bis 7 bleiben in diesem Schritt ausdrücklich unberechnet. Es werden weiterhin keine Fakturierungsdaten geschrieben.

Die Fakturierungsoberfläche ist als moderne responsive Karten-/Tabellenansicht umgesetzt. Nach Klick auf **Vorschau** wird der ausgewählte Auftrag oberhalb der Trefferliste gerendert und über einen URL-Fragmentanker direkt angesprungen. Ein Rücksprung **Zur Auftragsliste** führt wieder zur Trefferliste. Damit bleibt die Auswahl auch bei langen Auftragslisten unmittelbar bedienbar, ohne manuelles Scrollen bis zum Seitenende.

Das historische Tool erzeugt Dokumente über Microsoft-Word-COM und verwendet lokale Outlook-Integration. Diese Windows-spezifischen Komponenten werden auf dem Linux-Webserver nicht übernommen. Rechnungsdokumente werden später serverseitig neu erzeugt; E-Mail-, SEPA- und XRechnungs-Funktionen werden als separate Migrationsschritte behandelt. Rechnungsnummern und andere abrechnungsrelevante Schreibvorgänge müssen in der Webanwendung transaktions- und mehrbenutzersicher implementiert werden.

Für das Rechnungstool wird ein eigenes Benutzerhandbuch in `docs/RECHNUNGSTOOL_HANDBUCH.md` geführt. Technische Informationen bleiben Bestandteil dieser Datei. Die verwendeten und bestätigten SQL-Abfragen sowie Berechtigungs-Statements werden weiterhin zentral in `docs/SQL_WIKI.md` gesammelt, damit keine zweite konkurrierende SQL-Dokumentation entsteht.

## 10. Dokumentationspflege

Die vier Dokumentationsziele werden im Classic-Frontend über eine linke Direktleiste und im Modern-Frontend über Direktbuttons in der Kopfleiste angeboten. Die Links verwenden `target="_blank"` mit `rel="noopener"` und öffnen daher bewusst einen neuen Browser-Tab statt eines internen Workspace-Fensters.

Diese Datei ist die technische Quelle für die spätere Projektdokumentation. Bei Änderungen an Architektur, Tabellen, Beziehungen, Rechten, Geschäftslogik oder Modulen muss sie zusammen mit dem Code aktualisiert werden.

Das allgemeine Benutzerhandbuch wird parallel in `docs/HANDBUCH.md` gepflegt. Für das Rechnungstool existiert zusätzlich `docs/RECHNUNGSTOOL_HANDBUCH.md`. `docs/SQL_WIKI.md` bleibt das gemeinsame SQL-Statement-Wiki für die gesamte Webanwendung einschließlich Rechnungstool. Dort werden bestätigte Access-Abfragen, fachlich relevante direkte SQL-Abfragen und Berechtigungs-Statements mit kurzer Erklärung gesammelt. Die vier Markdown-Dateien werden über feste Links in der Webanwendung angezeigt und können nach Abschluss der Migration als Word- oder PDF-Dokument ausgegeben werden. Die Dokumentationsansichten einschließlich SQL-Wiki besitzen in Classic und Modern eine Volltext-Suche mit Trefferanzahl sowie Vor-/Zurück-Navigation zwischen Fundstellen. Abschnitt 10 bleibt bewusst der letzte Hauptabschnitt dieser Datei; neue technische Themen werden davor eingeordnet.
