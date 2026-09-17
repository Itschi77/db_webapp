# Handbuch Rechnungstool

Dieses Handbuch beschreibt ausschließlich das in die Webanwendung integrierte Rechnungstool. Es wird parallel zur Migration des bisherigen VB.NET-Rechnungstools fortgeschrieben. Funktionen werden erst dann als verfügbar beschrieben, wenn sie in der Webanwendung tatsächlich umgesetzt und getestet sind.

## 1. Zweck

Das Rechnungstool dient der Ermittlung abrechenbarer Aufträge und Auftragspositionen, der Berechnung von Rechnungspositionen sowie später der eigentlichen Rechnungserstellung. Dazu gehören unter anderem Festpreise, Abrechnungsintervalle, Staffeln, Accountingwerte, Domainkonditionen, Rabatte, Umsatzsteuer, Skonto und Lastschriften.

Die Webmigration erfolgt schrittweise. Der vorhandene XLSX-Rechnungslauf-Export ist davon getrennt und bleibt als Auswertungsfunktion bestehen.

## 2. Zugriff und Sicherheitsprinzip

Das Rechnungstool wird über **Rechnungstool starten** im Hauptmenü geöffnet. Der Einstieg führt auf `/fakturierung`. Zusätzlich zur allgemeinen Berechtigung `DB-Webapp-Users` ist die AD-Gruppe `DB-Webapp-Rechnungstool` erforderlich. Fehlt diese Zusatzberechtigung, bleibt der Menüpunkt deaktiviert und ein direkter Aufruf des Rechnungstools wird serverseitig mit HTTP 403 verweigert. Im Rechnungstool wird der über Windows-SSO erkannte Benutzer angezeigt.

Während der Migration gilt: Berechnungslogik wird zuerst im Test- bzw. Vorschaumodus umgesetzt. Solange ein Teil noch nicht produktiv freigegeben ist, werden keine echten Rechnungen erzeugt und keine abrechnungsrelevanten Daten verändert.

Produktive Schreibvorgänge werden erst freigeschaltet, nachdem die Ergebnisse der Webberechnung mit dem bisherigen Rechnungstool anhand derselben Aufträge geprüft wurden.

## 3. Auftragsliste

Beim Öffnen ist als Zeitraum der komplette Vormonat vorbelegt, entsprechend dem bisherigen Rechnungstool. **Von** und **Bis** können geändert werden. Über **Art** stehen derzeit die drei bestätigten Auswahlarten des Alttools zur Verfügung: **Nachträglich abrechnen**, **Im Voraus abrechnen** und **Domainaufträge**. Die Suche kann nach Auftragsnummer, Kundennummer, Rechnungsanschrift oder Auftragsbeschreibung einschränken.

Mit **Auftragsliste laden** wird ausschließlich lesend ermittelt, welche Aufträge nach den bestätigten Alttool-Filtern in den Zeitraum fallen. Angezeigt werden unter anderem letzte Rechnung, Auftrag, Kunde, Beschreibung, Fakturierbeginn, Stornodatum und E-Mail-Status. Pro Aufruf werden höchstens 500 Treffer angezeigt.

## 4. Vorschau und Testlauf

Über **Vorschau** wird ein einzelner Auftrag geöffnet. Die Seite springt dabei direkt in den Vorschau-Bereich; die Vorschau steht oberhalb der Trefferliste, damit nach der Auswahl kein Scrollen bis ans Seitenende nötig ist. Über **Zur Auftragsliste** gelangt man direkt zurück zur Trefferliste. Die aktuelle Phase zeigt dessen Auftragspositionen mit Menge, gespeichertem Netto-Endpreis, Rabatt, Umsatzsteuer, Fakturierungszeitraum, Abrechnungsart und Staffelreferenzen. Zusätzlich wird angezeigt, ob bzw. wie oft die Position laut `tblAuftragPosBerechnet` bereits berechnet wurde und welches das letzte Berechnungsdatum ist.

Diese Tabelle ist die Rohdatenbasis für den Paritätsvergleich und noch keine fertige Rechnung. Intervallberechnung, Accountingmengen, Staffelpreise, Domainpreise, Vorberechnung sowie endgültige Netto-/Steuer-/Bruttosummen folgen schrittweise. Der Testlauf verändert keine Abrechnungsdaten. Abweichungen zum bisherigen Tool müssen vor einer produktiven Freigabe geklärt werden.

## 5. Aktueller Stand und noch nicht produktiv freigegeben

Der geschützte Einstieg und die erste lesende Auftrags-/Positionsvorschau sind umgesetzt. Der Rechnungstool-Bereich verwendet unabhängig vom gewählten Hauptfrontend eine moderne, responsive Karten-/Tabellenansicht. Auswahl und Rohdaten können damit bereits mit dem bisherigen Rechnungstool verglichen werden.

Noch nicht freigegeben sind die vollständige Positionsberechnung, produktive Rechnungserzeugung, Rechnungsnummernvergabe, PDF-Erzeugung, SEPA-Dateien, E-Mail-Versand, XRechnung und Mahnwesen.

Dieses Kapitel wird bei jeder umgesetzten Funktion ergänzt, damit das Bedienhandbuch stets dem tatsächlich freigegebenen Funktionsstand entspricht.

## 6. Dokumentation und SQL

Technische Details des Rechnungstools werden in der allgemeinen **Technischen Doku** gepflegt. Alle bestätigten SQL-Abfragen und Berechtigungs-Statements des Rechnungstools werden im bestehenden **SQL-Statement-Wiki** ergänzt. Dieses Handbuch enthält dagegen nur den tatsächlichen Bedienablauf des Rechnungstools.
