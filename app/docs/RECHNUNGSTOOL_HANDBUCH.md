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

Mit **Auftragsliste laden** wird ausschließlich lesend ermittelt, welche Aufträge nach den bestätigten Alttool-Filtern in den Zeitraum fallen. Angezeigt werden unter anderem letzte Rechnung, Auftrag, Kunde, Beschreibung, Fakturierbeginn, Stornodatum und E-Mail-Status. Pro Aufruf werden höchstens 500 Treffer angezeigt.

Im **Classic-Frontend** wird die Auftragsliste nicht dauerhaft unter der Rechnungslauf-Maske eingeblendet. Dort bleibt die Bedienung bewusst nah am bisherigen Tool; die Listenansicht ist derzeit im modernen Frontend verfügbar. Ein direkter Link **Moderne Ansicht** schaltet aus dem Classic-Rechnungstool auf die moderne Darstellung um.

## 4. Vorschau und Testlauf

Über **Vorschau** wird ein einzelner Auftrag geöffnet. Die Seite springt dabei direkt in den Vorschau-Bereich; die Vorschau steht oberhalb der Trefferliste, damit nach der Auswahl kein Scrollen bis ans Seitenende nötig ist. Über **Zur Auftragsliste** gelangt man direkt zurück zur Trefferliste. Die aktuelle Phase zeigt dessen Auftragspositionen mit Menge, gespeichertem Netto-Endpreis, Rabatt, Umsatzsteuer, Fakturierungszeitraum, Abrechnungsart und Staffelreferenzen. Zusätzlich wird angezeigt, ob bzw. wie oft die Position laut `tblAuftragPosBerechnet` bereits berechnet wurde und welches das letzte Berechnungsdatum ist.

Für normale Festpreispositionen (`intStaffelTyp = 0`) ist die erste Berechnungsengine umgesetzt. Sie bildet die Berechnungstermine aus `datFakturierAb`, Abrechnungsdimension und Intervall nach, berücksichtigt `datFakturierBis` sowie ein Stornodatum des Auftrags und berechnet Menge × gespeichertem Endpreis, Positionsrabatt und Umsatzsteuer. Bereits zu demselben Berechnungstermin gespeicherte Zeilen aus `tblAuftragPosBerechnet` werden erkannt; stimmt der historische Betrag nicht mit der neuen Berechnung überein, wird die Preisabweichung wie im Alttool als Konflikt markiert. Bereits berechnete Positionen fließen nicht erneut in die Vorschau-Summe ein.

Die Option **Accountings berücksichtigen** ist nun wirksam. Ist sie ausgeschaltet, werden Accountingpositionen wie im alten Rechnungstool übersprungen. Ist sie eingeschaltet, werden inzwischen alle Staffeltypen 1 bis 7 lesend bewertet: Typ 1 nutzt Monatsnutzung und Preisstufe, Typ 2 Freimenge/Basispreis/Einheitspreis, Typ 3 die Dialin-EVN-Zeitbewertung, Typ 4 bzw. 7 die Bandbreitenbewertung nach MAX(In/Out) bzw. SUM(In+Out), Typ 5 die Domainkonditionen und Typ 6 die Bereichsstaffel. Bei Domains werden aktive Domain-Anbindungen, Registrierungsdatum, Einrichtungs-/Anfangsphase, reguläres Intervall sowie positionsbezogene Rabatte berücksichtigt. Die Vorschau zeigt Nutzung/Menge, ermittelten Basisbetrag, Rabatt, Umsatzsteuer und den Vergleich mit bereits berechneten Beträgen. Der Testlauf bildet nun außerdem die Vorberechnung/Nachberechnung nach. Bei aktiver `datVorberechnenBis` wird der Rechnungszeitraum um einen Monat vorgezogen. Für Staffeltyp 1 zeigt die Vorschau die bisher vorausbezahlte Staffel aus `tblAccountingKonto`, die aktuell erreichte Staffel und eine nötige Erstattung/Nachberechnung. Der Accounting-Stand für den Folgemonat wird nur simuliert und nicht gespeichert. Der Testlauf verändert keine Abrechnungsdaten; insbesondere werden weder `tblAccountingKonto` noch historische Dialin-Preiscaches aktualisiert. Die Vorberechnungslogik ist damit im read-only Paritätsschritt enthalten. Abweichungen zum bisherigen Tool müssen vor einer produktiven Freigabe geklärt werden.

Unter der Positionsvorschau wird für den ausgewählten Auftrag nun ein **kompletter Auftragstestlauf** angezeigt. Er enthält Rechnungsdatum, Fälligkeit, Rechnungsempfänger, Zahlungsbedingung, Papier-/E-Mail-Kennzeichen und DATEV-Kundenkonto. Nur noch nicht berechnete Termine werden zu neuen Rechnungszeilen. Festpreis-/Staffelzeilen, Rabattzeilen sowie Erstattungs- und Nachberechnungszeilen werden getrennt dargestellt und anschließend nach Netto, Umsatzsteuer und Brutto summiert. Vorhandene Skontostufen werden ebenfalls berechnet. Fehlen notwendige Stammdaten, besteht ein Preis-Konflikt oder schlägt ein Accounting fehl, steht der Auftragstestlauf auf **blockiert** und nennt die Ursachen. Hinweise, die das historische Tool nicht selbst als harten Konsistenzfehler behandelt, werden separat ausgegeben. Bankdaten erscheinen dabei nur maskiert.

Das in der Maske wählbare **Rechnungsdatum** wird für die Testrechnung und die daraus berechneten Fälligkeits-/Skontodaten verwendet. Eine Rechnungsnummer wird im Testlauf nicht vergeben.

### Kunden- und Gesamttestlauf

In der modernen Ansicht stehen zusätzlich **Kunde testen** und **Gesamtlauf** zur Verfügung. Für **Kunde testen** muss eine Kundennummer eingetragen sein; anschließend werden alle im gewählten Zeitraum und Abrechnungstyp passenden Aufträge dieses Kunden geprüft. **Gesamtlauf** prüft alle passenden Aufträge des gewählten Zeitraums und Abrechnungstyps. Die Auswertung zeigt Anzahl Kunden/Aufträge, fakturierbare Aufträge, Aufträge ohne neue Berechnung, blockierte Aufträge, Hinweise, Dokumentzeilen sowie Netto/Steuer/Brutto der fakturierbaren Aufträge. In der Ergebnistabelle kann jeder Auftrag für die detaillierte Einzelprüfung geöffnet werden. Ein blockierter Auftrag beendet den Gesamttestlauf nicht.

Im Classic-Modus ist der bisher deaktivierte **Start**-Button nun für read-only Testläufe aktiv. Ist eine Auftragsnummer gesetzt, wird nur dieser Auftrag geprüft; ist eine Kundennummer gesetzt, erfolgt ein Kundenlauf; bei Auftrag 0 und Kunde 0 wird der Gesamttestlauf gestartet. Unterhalb des Rechnungstools zeigt die **Tägliche Konsistenzprüfung** den automatisch um 08:00 Uhr erzeugten Prüfbericht mit direkten Links auf die betroffenen Aufträge. Die Links öffnen die ausführliche Ansicht in einem eigenen App-Fenster.

### Historischer Paritätsvergleich

Im Feld **Rechnungsnummer oder interne Rechnungs-ID** kann eine vorhandene Altrechnung ausgewählt werden. **Rechnung vergleichen** wiederholt deren historisch gespeicherte Berechnungstermine mit der aktuellen Weblogik. Netto, Steuer und Brutto werden ebenso gegenübergestellt wie jede einzelne Position und ihr Rabatt. Eine Differenz ab einem Cent, eine fehlende Web-Zeile oder eine zusätzliche neu berechnete Zeile wird deutlich als Abweichung markiert. Der Vergleich ist vollständig lesend. Da er heutige Stammdaten, Preise und noch vorhandene Accountingwerte verwendet, kann eine Abweichung auch eine spätere Datenänderung und nicht zwingend einen Programmfehler bedeuten.

## 5. Aktueller Stand und noch nicht produktiv freigegeben

Der geschützte Einstieg und die erste lesende Auftrags-/Positionsvorschau sind umgesetzt. Der Rechnungstool-Bereich verwendet unabhängig vom gewählten Hauptfrontend eine moderne, responsive Karten-/Tabellenansicht. Auswahl und Rohdaten können damit bereits mit dem bisherigen Rechnungstool verglichen werden.

Noch nicht freigegeben sind die produktive Rechnungserzeugung, Rechnungsnummernvergabe, PDF-Erzeugung, SEPA-Dateien, E-Mail-Versand, XRechnung und Mahnwesen. Der komplette Auftragstestlauf bleibt ausdrücklich read-only.

Dieses Kapitel wird bei jeder umgesetzten Funktion ergänzt, damit das Bedienhandbuch stets dem tatsächlich freigegebenen Funktionsstand entspricht.

## 6. Dokumentation und SQL

Technische Details des Rechnungstools werden in der allgemeinen **Technischen Doku** gepflegt. Alle bestätigten SQL-Abfragen und Berechtigungs-Statements des Rechnungstools werden im bestehenden **SQL-Statement-Wiki** ergänzt. Dieses Handbuch enthält dagegen nur den tatsächlichen Bedienablauf des Rechnungstools.
