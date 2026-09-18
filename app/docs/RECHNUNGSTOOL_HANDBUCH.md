# Handbuch Rechnungstool

Dieses Handbuch beschreibt ausschließlich das in die Webanwendung integrierte Rechnungstool. Es wird parallel zur Migration des bisherigen VB.NET-Rechnungstools fortgeschrieben. Funktionen werden erst dann als verfügbar beschrieben, wenn sie in der Webanwendung tatsächlich umgesetzt und getestet sind.

## 1. Zweck

Das Rechnungstool dient der Ermittlung abrechenbarer Aufträge und Auftragspositionen, der Berechnung von Rechnungspositionen sowie später der eigentlichen Rechnungserstellung. Dazu gehören unter anderem Festpreise, Abrechnungsintervalle, Staffeln, Accountingwerte, Domainkonditionen, Rabatte, Umsatzsteuer, Skonto und Lastschriften.

Die Webmigration erfolgt schrittweise. Der vorhandene XLSX-Rechnungslauf-Export ist davon getrennt und bleibt als Auswertungsfunktion bestehen.

## 2. Zugriff und Sicherheitsprinzip

Das Rechnungstool wird über **Rechnungstool starten** im Hauptmenü geöffnet. Der Einstieg führt auf `/fakturierung`. Zusätzlich zur allgemeinen Berechtigung `DB-Webapp-Users` ist die AD-Gruppe `DB-Webapp-Rechnungstool` erforderlich. Fehlt diese Zusatzberechtigung, bleibt der Menüpunkt deaktiviert und ein direkter Aufruf des Rechnungstools wird serverseitig mit HTTP 403 verweigert. Im Rechnungstool wird der über Windows-SSO erkannte Benutzer angezeigt.

Während der Migration gilt: Berechnungslogik wird zuerst im Test- bzw. Vorschaumodus umgesetzt. Solange ein Teil noch nicht produktiv freigegeben ist, werden keine echten Rechnungen erzeugt und keine abrechnungsrelevanten Daten verändert.

Produktive Schreibvorgänge werden erst freigeschaltet, nachdem die Ergebnisse der Webberechnung mit dem bisherigen Rechnungstool anhand derselben Aufträge geprüft wurden.

## 3. Bedienoberfläche und Auftragsliste

Die Classic-Ansicht orientiert sich bewusst an der Bedienstruktur des bisherigen Rechnungstools. Sie verwendet die Bereiche **Datumsbereich**, **Test/Monatslauf**, **Auftrags/Kundennummer** und **Logging**. Noch nicht implementierte produktive Funktionen bleiben dort sichtbar, aber deaktiviert und eindeutig beschriftet. Die moderne Ansicht verwendet dagegen keine Nachbildung der alten Registerkarten, sondern eine kompakte Filterleiste mit einer geteilten Arbeitsansicht: Auftragsliste links, ausgewählter Auftrag mit Positionen direkt rechts.

Beim Öffnen ist als Zeitraum der komplette Vormonat vorbelegt, entsprechend dem bisherigen Rechnungstool. **Von** und **Bis** können geändert werden. Über **Art** stehen derzeit die drei bestätigten Auswahlarten des Alttools zur Verfügung: **Nachträglich abrechnen**, **Im Voraus abrechnen** und **Domainaufträge**. Die Suche kann nach Auftragsnummer, Kundennummer, Rechnungsanschrift oder Auftragsbeschreibung einschränken.

Mit **Liste aktualisieren** wird ausschließlich lesend ermittelt, welche Aufträge nach den bestätigten Alttool-Filtern in den Zeitraum fallen. Die moderne Ansicht zeigt standardmäßig nur **Abrechenbar**: Aufträge mit mindestens einer neuen Dokumentzeile und einem fehlerfreien vollständigen Testlauf. Über den Umschalter **Alle Kandidaten** werden zusätzlich bereits vollständig berechnete, blockierte oder für den Zeitraum noch nicht fällige Aufträge angezeigt. Die Zähler neben beiden Ansichten machen den Unterschied unmittelbar sichtbar. Die fachliche Klassifizierung verwendet dieselbe Berechnung wie die Einzelvorschau und wird für fünf Minuten zwischengespeichert. Angezeigt werden unter anderem letzte Rechnung, Auftrag, Kunde und Beschreibung. Pro Aufruf werden höchstens 500 Kandidaten geprüft.

Im **Classic-Frontend** wird die Auftragsliste nicht dauerhaft unter der Rechnungslauf-Maske eingeblendet. Dort bleibt die Bedienung bewusst nah am bisherigen Tool; die Listenansicht ist derzeit im modernen Frontend verfügbar. Ein direkter Link **Moderne Ansicht** schaltet aus dem Classic-Rechnungstool auf die moderne Darstellung um.

## 4. Vorschau und Testlauf

In der modernen Auftragsliste besitzt jeder Auftrag rechts den sichtbaren Hinweis **Vorschau**; ein Klick auf die Auftragszeile öffnet die ausführliche Ansicht direkt daneben. Das Rechnungstool startet dafür als nahezu bildschirmfüllendes App-Fenster. Die aktuelle Phase zeigt dessen Auftragspositionen mit Menge, gespeichertem Netto-Endpreis, Rabatt, Umsatzsteuer, Fakturierungszeitraum, Abrechnungsart und Staffelreferenzen. Zusätzlich wird angezeigt, ob bzw. wie oft die Position laut `tblAuftragPosBerechnet` bereits berechnet wurde und welches das letzte Berechnungsdatum ist.

Für normale Festpreispositionen (`intStaffelTyp = 0`) ist die erste Berechnungsengine umgesetzt. Sie bildet die Berechnungstermine aus `datFakturierAb`, Abrechnungsdimension und Intervall nach, berücksichtigt `datFakturierBis` sowie ein Stornodatum des Auftrags und berechnet Menge × gespeichertem Endpreis, Positionsrabatt und Umsatzsteuer. Bereits zu demselben Berechnungstermin gespeicherte Zeilen aus `tblAuftragPosBerechnet` werden erkannt; stimmt der historische Betrag nicht mit der neuen Berechnung überein, wird die Preisabweichung wie im Alttool als Konflikt markiert. Bereits berechnete Positionen fließen nicht erneut in die Vorschau-Summe ein.

Die Option **Accountings berücksichtigen** ist nun wirksam. Ist sie ausgeschaltet, werden Accountingpositionen wie im alten Rechnungstool übersprungen. Ist sie eingeschaltet, werden inzwischen alle Staffeltypen 1 bis 7 lesend bewertet: Typ 1 nutzt Monatsnutzung und Preisstufe, Typ 2 Freimenge/Basispreis/Einheitspreis, Typ 3 die Dialin-EVN-Zeitbewertung, Typ 4 bzw. 7 die Bandbreitenbewertung nach MAX(In/Out) bzw. SUM(In+Out), Typ 5 die Domainkonditionen und Typ 6 die Bereichsstaffel. Bei Domains werden aktive Domain-Anbindungen, Registrierungsdatum, Einrichtungs-/Anfangsphase, reguläres Intervall sowie positionsbezogene Rabatte berücksichtigt. Die Vorschau zeigt Nutzung/Menge, ermittelten Basisbetrag, Rabatt, Umsatzsteuer und den Vergleich mit bereits berechneten Beträgen. Der Testlauf bildet nun außerdem die Vorberechnung/Nachberechnung nach. Bei aktiver `datVorberechnenBis` wird der Rechnungszeitraum um einen Monat vorgezogen. Für Staffeltyp 1 zeigt die Vorschau die bisher vorausbezahlte Staffel aus `tblAccountingKonto`, die aktuell erreichte Staffel und eine nötige Erstattung/Nachberechnung. Der Accounting-Stand für den Folgemonat wird nur simuliert und nicht gespeichert. Der Testlauf verändert keine Abrechnungsdaten; insbesondere werden weder `tblAccountingKonto` noch historische Dialin-Preiscaches aktualisiert. Die Vorberechnungslogik ist damit im read-only Paritätsschritt enthalten. Abweichungen zum bisherigen Tool müssen vor einer produktiven Freigabe geklärt werden.

Unter der Positionsvorschau wird für den ausgewählten Auftrag nun ein **kompletter Auftragstestlauf** angezeigt. Er enthält Rechnungsdatum, Fälligkeit, Rechnungsempfänger, Zahlungsbedingung, Papier-/E-Mail-Kennzeichen und DATEV-Kundenkonto. Nur noch nicht berechnete Termine werden zu neuen Rechnungszeilen. Festpreis-/Staffelzeilen, Rabattzeilen sowie Erstattungs- und Nachberechnungszeilen werden getrennt dargestellt und anschließend nach Netto, Umsatzsteuer und Brutto summiert. Vorhandene Skontostufen werden ebenfalls berechnet. Fehlen notwendige Stammdaten, besteht ein Preis-Konflikt oder schlägt ein Accounting fehl, steht der Auftragstestlauf auf **blockiert** und nennt die Ursachen. Hinweise, die das historische Tool nicht selbst als harten Konsistenzfehler behandelt, werden separat ausgegeben. Bankdaten erscheinen dabei nur maskiert.

Das in der Maske wählbare **Rechnungsdatum** wird für die Testrechnung und die daraus berechneten Fälligkeits-/Skontodaten verwendet. Eine Rechnungsnummer wird im Testlauf nicht vergeben.

### PDF-Rechnungsvorschau

Im Kopf des vollständigen Auftragstestlaufs öffnet **PDF-Vorschau öffnen** jederzeit ein eigenständiges A4-Dokument in einem neuen Fenster. Enthält der ausgewählte Zeitraum keine neuen Rechnungspositionen, wird dies als Nullrechnung kenntlich gemacht; der Button bleibt trotzdem sichtbar. Die serverseitig erzeugte PDF übernimmt Rechnungsempfänger, Rechnungsdatum, Positionen, Rabatte, Umsatzsteuer, Netto-/Bruttosummen, Zahlungsbedingung, Skonto, Leistungszeitraum und die wesentlichen Fußzeilen der bisherigen Papierrechnung. Sie ist mehrfach als **VORSCHAU / KEINE RECHNUNG** gekennzeichnet. Die angezeigte Rechnungsnummer ist nur simuliert und wird weder reserviert noch gespeichert; auch die PDF selbst wird nicht in der MIDAS-Ablage abgelegt.

Als unveränderte Referenz liegen auf Janus die schreibgeschützte Kopie der produktiven Vorlage unter `/srv/dbapp/reference/midas-invoice-templates/originals/Rechnung-4.dot` und die Musterrechnung `2026001462` unter `samples/`. Für Layoutarbeiten dient ausschließlich `working/Rechnung-4.dot`. Die Originalfreigabe `/mnt/midas-bh` bleibt unangetastet.

### Rechnungsnummern-Simulation

Die Box **Rechnungsnummern-Simulation** zeigt zum gewählten Rechnungsdatum den Jahresnummernkreis und die rein lesend ermittelte nächste Nummer. Das Alttool verwaltet den Jahreszähler in `tblRechnungsNummern`; die Webapp liest diesen Zähler und vergleicht ihn mit der höchsten bereits gespeicherten Rechnung. Fehlt dem Webapp-Benutzer das Leserecht, wird dies als Warnung angezeigt und vorübergehend `MAX(intRechNr)+1` verwendet. Das historisch bestätigte Format ist `JJJJ` plus sechsstelliger Jahreszähler, beispielsweise `2026001463`. Die Anzeige reserviert oder vergibt nichts. Solange das Alttool produktiv arbeitet, kann es die angezeigte Nummer jederzeit zuerst verwenden; vor einer späteren Speicherung muss die Nummer daher innerhalb derselben Datenbanktransaktion erneut ermittelt und gesperrt werden. Warnungen erscheinen außerdem bei doppelten Nummern, abweichenden Jahrespräfixen, ausgeschöpftem Nummernkreis oder einem Rechnungsdatum in einem abgeschlossenen Jahr.

### Kunden- und Gesamttestlauf

In der modernen Ansicht stehen zusätzlich **Kunde testen** und **Gesamtlauf** zur Verfügung. Für **Kunde testen** muss eine Kundennummer eingetragen sein; anschließend werden alle im gewählten Zeitraum und Abrechnungstyp passenden Aufträge dieses Kunden geprüft. **Gesamtlauf** prüft alle passenden Aufträge des gewählten Zeitraums und Abrechnungstyps. Die Auswertung zeigt Anzahl Kunden/Aufträge, fakturierbare Aufträge, Aufträge ohne neue Berechnung, blockierte Aufträge, Hinweise, Dokumentzeilen sowie Netto/Steuer/Brutto der fakturierbaren Aufträge. In der Ergebnistabelle kann jeder Auftrag für die detaillierte Einzelprüfung geöffnet werden. Ein blockierter Auftrag beendet den Gesamttestlauf nicht.

Im Classic-Modus ist der bisher deaktivierte **Start**-Button nun für read-only Testläufe aktiv. Ist eine Auftragsnummer gesetzt, wird nur dieser Auftrag geprüft; ist eine Kundennummer gesetzt, erfolgt ein Kundenlauf; bei Auftrag 0 und Kunde 0 wird der Gesamttestlauf gestartet. Unterhalb des Rechnungstools zeigt die **Tägliche Konsistenzprüfung** den automatisch um 08:00 Uhr erzeugten Prüfbericht mit direkten Links auf die betroffenen Aufträge. Die Links öffnen die ausführliche Ansicht in einem eigenen App-Fenster. Zusätzlich überwacht der Bericht entsprechend den aktiven Regeln der bisherigen SQL-Prüfungen die Aktualität von Switch-Accounting, HERMES-IP-Accounting und Accounting-Monatssummen sowie Abweichungen und veraltete Aktualisierungen bei aktiven Portbeschreibungen. Systemprobleme stehen ohne Auftragslink am Anfang der Tabelle. Fehlende SQL-Leserechte erscheinen als eigener Prüfpunkt, statt den gesamten Tageslauf abzubrechen.

### Historischer Paritätsvergleich

Im Feld **Rechnungsnummer oder interne Rechnungs-ID** kann eine vorhandene Altrechnung ausgewählt werden. **Rechnung vergleichen** wiederholt deren historisch gespeicherte Berechnungstermine mit der aktuellen Weblogik. Netto, Steuer und Brutto werden ebenso gegenübergestellt wie jede einzelne Position und ihr Rabatt. Eine Differenz ab einem Cent, eine fehlende Web-Zeile oder eine zusätzliche neu berechnete Zeile wird deutlich als Abweichung markiert. Der Vergleich ist vollständig lesend. Da er heutige Stammdaten, Preise und noch vorhandene Accountingwerte verwendet, kann eine Abweichung auch eine spätere Datenänderung und nicht zwingend einen Programmfehler bedeuten.

Der Paritätsvergleich ist in erster Linie ein **Abnahme- und Sicherheitswerkzeug für die Migration**. Vor der produktiven Freigabe wird damit nachgewiesen, dass die Webapp für dieselben historischen Leistungen dieselben Ergebnisse wie das Alttool erzeugt. Geprüft werden sollen repräsentative Festpreis-, Rabatt-, Mehrpositions-, Staffel-/Accounting-, Domain- und Vorausberechnungsrechnungen. Im späteren Tagesgeschäft wird die Funktion nur noch für Reklamationen, außergewöhnliche Rechnungen und zur Kontrolle nach Änderungen an Preisen oder Berechnungslogik benötigt. Nach erfolgreicher Einführung kann sie deshalb aus dem normalen Arbeitsbereich in einen Admin-/Diagnosebereich verschoben werden.

Über **Systematische Stichprobe starten** wählt die Webapp automatisch sechs unterschiedliche aktuelle Altrechnungen aus: Festpreis, Rabatt, mehrere Positionen, Accounting/Staffel, Domain und Vorausberechnung. Die Ergebnistabelle zeigt Rechnungs- und Auftragsnummer, Netto- und Steuerdifferenz sowie abweichende, fehlende und zusätzliche Zeilen; die Rechnungsnummer öffnet den ausführlichen Einzelvergleich. Der bestätigte Lauf vom 18.09.2026 verglich `2026001462`, `2026001437`, `2026001456`, `2026001425`, `2026001461` und `2026001460`. Alle sechs Rechnungen stimmten ohne Netto-, Steuer-, Brutto- oder Zeilendifferenz überein.

## 5. Festgestellte Alttool-Ungereimtheiten und Schutzmaßnahmen der Webapp

Im Zuge der Migration wurden mehrere fachlich relevante Besonderheiten und Schwachstellen des bisherigen Rechnungstools bestätigt. Es handelt sich nicht um einen einzelnen Fehler, sondern überwiegend um historisch gewachsene Geschäftslogik, die über mehrere Datenbanken, Tabellen und Anwendungskomponenten verteilt ist. Diese Punkte werden in der Webapp ausdrücklich geprüft, damit sie nicht stillschweigend in die neue Lösung übernommen werden.

### Verteilte Kundendatenquellen

Die für eine Rechnung benötigten Kundendaten stammen nicht aus einer einzigen Datenbank:

- `accountings.dbo.tblAuftrag` liefert Auftrag, Kundennummer, Zahlungsbedingung und Rechnungsanschrift-ID.
- `topsnetdb_safe.dbo.tblKunde` ist die verbindliche Quelle für den Kundenstamm und das DATEV-Kundenkonto.
- `accountings.dbo.tblRechnungsanschrift` enthält den tatsächlichen Rechnungsempfänger mit Anschrift, E-Mail, USt-ID und Bankdaten.
- `accountings.dbo.tblZahlungsbedingung` enthält die Zahlungsbedingung; sie stammt ausdrücklich nicht aus der Kundenstammdatenbank.

Diese Aufteilung war im Altsystem nicht an einer zentralen Stelle dokumentiert. Die Webapp kapselt die Prüfung deshalb in einer eigenen Kundenkonsistenzprüfung und behandelt die genannten Tabellen als verbindliche Datenquellen.

### Abweichende oder fremde Rechnungsanschriften

Das Alttool berücksichtigt einen Auftrag nur dann korrekt, wenn sowohl `tblAuftrag.intAnschriftID = tblRechnungsanschrift.intID` als auch `tblAuftrag.intKID = tblRechnungsanschrift.intKID` gilt. Historische Datensätze zeigen, dass Rechnungsanschriften vorhanden sein können, die zu einer anderen Kundennummer gehören.

Die Webapp prüft diese Zuordnung vor der Fakturierung. Eine fehlende Rechnungsanschrift oder eine abweichende Kundennummer blockiert den Auftrag. Zusätzlich prüft `InvoiceCustomerConsistencyService` die aktiven Rechnungstool-Aufträge regelmäßig gegen `topsnetdb_safe.dbo.tblKunde`. Der Kontrolllauf vom 18.09.2026 ergab für die aktive Kundenstamm-/Adresszuordnung keine Fehler.

### DATEV-Daten werden vorab validiert

Im Alttool konnten fehlende DATEV-Angaben erst relativ spät im Ablauf zum Abbruch eines einzelnen Auftrags führen. Die Webapp prüft deshalb bereits im Auftragstestlauf sowohl das DATEV-Kundenkonto als auch die DATEV-Kontierung der abzurechnenden Positionen. Fehlen diese Daten, wird der Auftrag vor einer Rechnungserzeugung blockiert und der Grund sichtbar angezeigt.

### Prüf-Procedures mit Nebenwirkungen

Einige historische SQL-Prüf-Procedures sind keine reinen Prüfungen. Sie können unter anderem E-Mails versenden oder temporäre Tabellen erzeugen. Solche Procedures werden von der Webapp nicht für den täglichen Konsistenzlauf aufgerufen.

Die weiterhin fachlich relevanten Kontrollen wurden stattdessen als reine `SELECT`-Prüfungen nachgebildet. Dadurch verändert eine Konsistenzprüfung keine Abrechnungsdaten und löst keine alten Benachrichtigungsmechanismen aus.

### Rechnungsnummernlogik außerhalb der Datenbank

Die Vergabe der Rechnungsnummer befindet sich im Altsystem nicht in einer Stored Procedure. Die bestätigte Logik steckt in `komponenteFakturierungswesen.dll` und verwendet `tblRechnungsNummern` innerhalb einer laufenden Datenbanktransaktion.

Die Webapp bildet dieses Verhalten ausdrücklich nach. In Vorschau und Testlauf wird eine Nummer nur simuliert. Bei einer späteren produktiven Rechnung muss der Zähler innerhalb derselben Transaktion erneut gelesen, gesperrt und aktualisiert werden. Dadurch soll verhindert werden, dass eine simulierte oder zwischenzeitlich bereits verwendete Nummer geschrieben wird.

### Bankeinzug und SEPA waren nicht überall streng validiert

Die historische Logik prüft an mehreren Stellen eher das Vorhandensein von Datensätzen als die fachliche Vollständigkeit der enthaltenen Bankdaten. Dadurch können Kombinationen aus Bankeinzug, SEPA-Kennzeichen und unvollständigen Kontodaten erst spät auffallen.

Die Webapp prüft deshalb zusätzlich IBAN bzw. vorhandene Kontodaten, SEPA-Freigabe und die zum Auftrag passende Zahlungsart. Fehlende Pflichtangaben blockieren die spätere Rechnungserzeugung; schwächere Abweichungen werden als Hinweis angezeigt.

### E-Mail-Versand ohne gültige Empfängeradresse

Im Bestand existieren Aufträge mit aktiviertem E-Mail-Rechnungsversand, deren Rechnungsanschrift keine gültige E-Mail-Adresse enthält. Der tägliche Konsistenzlauf macht diese Fälle sichtbar. Vor einer späteren produktiven Erzeugung blockiert die Webapp E-Mail-Rechnungen ohne gültige Empfängeradresse.

### Veraltete oder deaktivierte Accounting-Prüfungen

Im Altcode sind mehrere frühere Kontrollen deaktiviert oder auskommentiert, unter anderem für Dial-in, SMS, STHS3 und eine alte BONN9-Benachrichtigung. Diese Prüfungen wurden nicht allein deshalb reaktiviert, weil sie noch im Quellcode vorhanden sind.

Die Webapp übernimmt nur die nachweislich aktiven fachlichen Kontrollen. Dazu gehören derzeit die Aktualität von Switch-Accounting, HERMES-IP-Accounting und Accounting-Monatssummen sowie Prüfungen der aktiven Portbeschreibungen.

### Verteilte Schreiblogik und Risiko von Teilzuständen

Eine produktive Fakturierung betrifft gleichzeitig Rechnungsnummer, `tblRechnung`, `tblAuftragPosBerechnet`, gegebenenfalls `tblAccountingKonto` sowie die PDF-Ablage. Würden diese Schritte unabhängig voneinander ausgeführt, könnten bei einem Fehler unvollständige Teilzustände entstehen.

Die Webapp schützt diesen Bereich deshalb zusätzlich durch `INVOICE_WRITES_ENABLED` und den `InvoiceWriteGuard`. Solange die Freigabe deaktiviert ist, bleiben Vorschau und Testläufe read-only. Nach einer späteren Freigabe werden die Datenbankänderungen transaktionssicher ausgeführt; bei einem Fehler werden Nummer und Fachdaten gemeinsam zurückgerollt.

## 6. Aktueller Stand und noch nicht produktiv freigegeben

Der geschützte Einstieg und die erste lesende Auftrags-/Positionsvorschau sind umgesetzt. Der Rechnungstool-Bereich verwendet unabhängig vom gewählten Hauptfrontend eine moderne, responsive Karten-/Tabellenansicht. Auswahl und Rohdaten können damit bereits mit dem bisherigen Rechnungstool verglichen werden.

Die transaktionssichere Schreiblogik für Rechnungsnummer, `tblRechnung`, `tblAuftragPosBerechnet` und `tblAccountingKonto` ist vorbereitet. Die Box **Produktive Schreiblogik** zeigt, ob SQL-Rechte und serverseitige Freigabe vollständig sind. Die SQL-Rechte und ein kontrollierter Testcommit wurden am 18.09.2026 erfolgreich geprüft. Solange `INVOICE_WRITES_ENABLED` deaktiviert ist, erscheint dennoch kein aktiver Erzeugen-Button und Vorschau/PDF bleiben rein lesend. Nach einer späteren Freigabe muss vor jeder Rechnung der eingeblendete Bestätigungstext exakt eingegeben werden; unmittelbar vor dem Schreiben wird der Auftrag innerhalb der Datenbanktransaktion erneut geprüft. Bei einem Fehler werden Nummer und sämtliche Fachdaten gemeinsam zurückgerollt.

Die getrennte PDF-Ablage ist technisch umgesetzt und mit Testrechnung `2026001464` geprüft. Verbindliche PDFs verwenden das Schema `Jahr/Rechnungen/Papier/Rechnungsnummer.pdf`; der gespeicherte Pfad öffnet die Datei direkt aus der Rechnungsdetailansicht. Die bestehende MIDAS-Freigabe bleibt read-only.

Im Auftragstestlauf wird ein Versand- und Zahlungsplan angezeigt. Er nennt Dokumentart, Papier- und E-Mail-Versand, Empfängeradresse, Zahlungsart sowie bei Lastschrift SEPA-Sequenz, Einzugsbetrag und Fälligkeit. Fehlende Versandart, ungültige E-Mail-Adresse, fehlende SEPA-Freigabe oder fehlende Bankdaten blockieren die spätere Erzeugung. Hinweise wie ein fehlendes Lastschrift-Auftragsmerkmal werden gesondert angezeigt. Diese Prüfung versendet noch nichts und setzt kein Versanddatum.

Noch nicht freigegeben sind die tatsächliche Nutzung dieser produktiven Rechnungserzeugung im normalen Bedienablauf, die Ausführung von E-Mail-Versand und Druckübergabe, die Erzeugung von SEPA-Dateien und das Mahnwesen.

### E-Rechnung: XRechnung und ZUGFeRD

Die E-Rechnungsunterstützung ist technisch umgesetzt und bleibt bis zur allgemeinen Produktivfreigabe rein lesend bzw. durch `INVOICE_WRITES_ENABLED` geschützt. Die moderne Rechnungstool-Ansicht zeigt für den ausgewählten Auftrag einen eigenen Bereich **E-Rechnung** mit der Bereitschaft für XRechnung und ZUGFeRD sowie den jeweils fehlenden Pflichtangaben.

**XRechnung** wird als CII im Profil XRechnung 3.0.2 erzeugt. Als Käuferreferenz wird `tblRechnungsanschrift.strLeitwegId` verwendet; `boolXRechnung` kennzeichnet Rechnungsanschriften, für die im späteren produktiven Lauf zwingend eine XRechnung erzeugt werden muss. Die elektronische Empfängeradresse stammt aus `strEmail`, eine vorhandene Lieferanten-ID aus `strLieferantenId`. Vor Ausgabe wird das XML dreistufig geprüft: XML-Schema, EN16931-Prüfung und anschließend mit dem offiziellen KoSIT-Validator 1.6.3 gegen die XRechnung-3.0.2-Konfiguration vom 31.08.2026. Schlägt eine dieser Prüfungen fehl, wird keine XRechnung ausgegeben.

**ZUGFeRD** wird im Profil EN16931 nach dem aktuellen ZUGFeRD-2.5.2-Stand erzeugt. Das validierte XML wird in die aus der Word-Vorlage erzeugte Rechnung eingebettet und daraus ein PDF/A-3u erzeugt. Nach dem Einbetten wird das XML wieder aus dem PDF gelesen und per SHA-256 mit den zuvor validierten Rechnungsdaten verglichen. Anschließend prüft veraPDF 1.30.2 das vollständige Dokument gegen PDF/A-3u. Ein nicht konformes PDF wird nicht ausgegeben bzw. im späteren Produktivlauf nicht gespeichert.

Die XML- und PDF/A-Vorschauen verwenden wie die normale PDF-Vorschau die simulierte Rechnungsnummer und verändern keine Abrechnungsdaten. Bei einer späteren produktiven Freigabe wird für `boolXRechnung=1` zusätzlich zur visuellen PDF eine `.xrechnung.xml` in der Rechnungsablage gespeichert. Für andere technisch geeignete Rechnungen wird die visuelle PDF als ZUGFeRD-PDF/A-3 mit eingebettetem EN16931-XML gespeichert.

Bewusst blockiert werden derzeit E-Rechnungen mit negativen Dokumentzeilen, solange diese nicht eindeutig als EN16931-Zuschlag/Rabatt modelliert sind, sowie Positionen mit 0 % Umsatzsteuer ohne eindeutig hinterlegten Steuerbefreiungsgrund. An diesen Stellen werden keine steuerlichen Bedeutungen aus Alt-Daten geraten. Die historische Rechnungsanschrift enthält außerdem kein eigenes Länderfeld; die derzeitige E-Rechnungsausgabe setzt daher deutsche Rechnungsanschriften voraus.

### Mahnwesen

Das Mahnwesen ist auf Basis der vorhandenen Felder in `accountings.dbo.tblRechnung` umgesetzt. Die Zentrale zeigt offene und überfällige Rechnungen, offenen Hauptbetrag, bestehende Mahnstufe, Mahnhistorie, strittige Fälle, Wiedervorlagen und Kundensperren. Für die aktuell offenen Daten werden die Restbeträge aus Rechnungsbetrag abzüglich Zahlungen, Gutschriften und Verlustabschreibungen berechnet; vorhandene Verzugszinsen und Mahngebühren werden separat berücksichtigt.

Die automatische Vorschlagslogik ist bewusst als betriebliche Konfiguration und nicht als Rechtsvorgabe umgesetzt. Standardmäßig wird Stufe 1 sieben Tage nach Fälligkeit vorgeschlagen, Stufe 2 vierzehn Tage nach Mahnung 1 und Stufe 3 vierzehn Tage nach Mahnung 2. Rechnungen mit Ratenzahlung oder als strittig markierte Rechnungen werden nicht automatisch eskaliert. Strittige Rechnungen können mit Grund und Wiedervorlagedatum versehen werden. Die Mahngebühr je neuer Stufe ist konfigurierbar und standardmäßig `0,00 €`; historische bereits gespeicherte Gebühren werden unverändert angezeigt.

Für jede fällige Mahnstufe kann ein PDF-Mahnschreiben als Vorschau erzeugt werden. Es enthält Empfänger, Rechnungsnummer, Rechnungs- und Fälligkeitsdatum, offenen Rechnungsbetrag, vorhandene bzw. zusätzlich vorgesehene Mahngebühren, Verzugszinsen, Gesamtbetrag und eine konfigurierbare Zahlungsfrist. Vorschauen tragen den Hinweis **VORSCHAU** und verändern keine Daten.

Die vorbereiteten produktiven Aktionen können Mahnstufe und Mahndatum buchen, zusätzliche Mahngebühren addieren, Rechnungen als strittig markieren bzw. wieder freigeben sowie eine Kundensperre über alle offenen Rechnungen eines Kunden setzen oder aufheben. Sämtliche Schreibvorgänge werden protokolliert. Sie bleiben bis zur abschließenden Produktivfreigabe durch `DUNNING_WRITES_ENABLED=false` serverseitig blockiert.

Die kontrollierte Bearbeitungsstufe vor der PDF-Erzeugung ist umgesetzt. In der modernen Ansicht können die Beschreibungstexte der erzeugten Dokumentzeilen sowie ein zusätzlicher Rechnungshinweis angepasst und anschließend ausdrücklich bestätigt werden. Netto, Umsatzsteuersatz, Steuerbetrag und Gesamtsummen sind dabei absichtlich nicht frei editierbar. Der bestätigte Bearbeitungsstand wird an einen Fingerprint aus Dokumentzeilen, Netto, Steuer und Brutto gebunden. Verändert sich danach die zugrunde liegende Berechnung, wird der Bearbeitungsstand automatisch als veraltet verworfen. Bestätigung und Verwerfen werden mit Benutzer und Änderungen im Anwendungslog protokolliert. Der Bearbeitungsstand selbst liegt nur in der Benutzersitzung und verändert keine Auftrags- oder Rechnungsdaten. PDF-Vorschau und spätere produktive PDF-Erzeugung verwenden denselben bestätigten Stand.

Die Rechnungsausgabe verwendet inzwischen nicht mehr die separat nachgebaute HTML-/DomPDF-Vorlage. Grundlage ist die aus der historischen `Rechnung-4.dot` unverändert nach DOCX konvertierte Word-Vorlage unter `resources/invoicing/Rechnung-4.docx`. Janus befüllt deren vorhandene Word-Formularfelder für Kundennummer, Auftragsnummer, Buchungskonto, E-Mail, USt-ID des Empfängers, Rechnungsdatum, Empfänger, Dokumentart, Rechnungsnummer, Zahlungsbedingung, Skonto und Rücklastschrifttext. Die Positionstabelle wird mit Zeilen im Stil der historischen Musterrechnung erweitert; Netto-, Steuer- und Bruttosummen werden aus dem bestätigten Testlauf übernommen. Anschließend konvertiert LibreOffice im App-Container das erzeugte DOCX im Headless-Modus nach PDF. Dadurch bleiben Logo, Kopfbereich, Textfelder, Firmen- und Bankfuß sowie die Seitengeometrie der bisherigen Vorlage erhalten. Vorschauen tragen im Dokumenttitel ausdrücklich den Zusatz **VORSCHAU**, während eine spätere produktive Rechnung dieselbe Vorlage ohne Vorschaukennzeichnung verwendet. Das originale `.dot` unter `reference/midas-invoice-templates/originals/` bleibt unverändert und dient weiterhin als Referenz.

Die frühere HTML-PDF-Vorlage bleibt vorerst nur als technische Altimplementierung im Projekt, wird aber weder für die PDF-Vorschau noch für die produktive Rechnungserzeugung verwendet. Die Quelle der in historischen Rechnungen angezeigten Artikelnummer in der Spalte **Position (Artikel)** ist noch nicht eindeutig bestätigt; Janus erfindet an dieser Stelle bewusst keine Zuordnung aus Produkt- oder Positions-ID.

Dieses Kapitel wird bei jeder umgesetzten Funktion ergänzt, damit das Bedienhandbuch stets dem tatsächlich freigegebenen Funktionsstand entspricht.

## 7. Dokumentation und SQL

Technische Details des Rechnungstools werden in der allgemeinen **Technischen Doku** gepflegt. Alle bestätigten SQL-Abfragen und Berechtigungs-Statements des Rechnungstools werden im bestehenden **SQL-Statement-Wiki** ergänzt. Dieses Handbuch enthält dagegen nur den tatsächlichen Bedienablauf des Rechnungstools.
