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

## 5. Aktueller Stand und noch nicht produktiv freigegeben

Der geschützte Einstieg und die erste lesende Auftrags-/Positionsvorschau sind umgesetzt. Der Rechnungstool-Bereich verwendet unabhängig vom gewählten Hauptfrontend eine moderne, responsive Karten-/Tabellenansicht. Auswahl und Rohdaten können damit bereits mit dem bisherigen Rechnungstool verglichen werden.

Noch nicht freigegeben sind die vollständige Positionsberechnung, produktive Rechnungserzeugung, Rechnungsnummernvergabe, PDF-Erzeugung, SEPA-Dateien, E-Mail-Versand, XRechnung und Mahnwesen.

Dieses Kapitel wird bei jeder umgesetzten Funktion ergänzt, damit das Bedienhandbuch stets dem tatsächlich freigegebenen Funktionsstand entspricht.

## 6. Dokumentation und SQL

Technische Details des Rechnungstools werden in der allgemeinen **Technischen Doku** gepflegt. Alle bestätigten SQL-Abfragen und Berechtigungs-Statements des Rechnungstools werden im bestehenden **SQL-Statement-Wiki** ergänzt. Dieses Handbuch enthält dagegen nur den tatsächlichen Bedienablauf des Rechnungstools.
