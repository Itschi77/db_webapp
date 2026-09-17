# Handbuch Rechnungstool

Dieses Handbuch beschreibt ausschließlich das in die Webanwendung integrierte Rechnungstool. Es wird parallel zur Migration des bisherigen VB.NET-Rechnungstools fortgeschrieben. Funktionen werden erst dann als verfügbar beschrieben, wenn sie in der Webanwendung tatsächlich umgesetzt und getestet sind.

## 1. Zweck

Das Rechnungstool dient der Ermittlung abrechenbarer Aufträge und Auftragspositionen, der Berechnung von Rechnungspositionen sowie später der eigentlichen Rechnungserstellung. Dazu gehören unter anderem Festpreise, Abrechnungsintervalle, Staffeln, Accountingwerte, Domainkonditionen, Rabatte, Umsatzsteuer, Skonto und Lastschriften.

Die Webmigration erfolgt schrittweise. Der vorhandene XLSX-Rechnungslauf-Export ist davon getrennt und bleibt als Auswertungsfunktion bestehen.

## 2. Zugriff und Sicherheitsprinzip

Das Rechnungstool wird über **Rechnungstool starten** im Hauptmenü geöffnet. Der Einstieg führt auf `/fakturierung`. Zusätzlich zur allgemeinen Berechtigung `DB-Webapp-Users` ist die AD-Gruppe `DB-Webapp-Rechnungstool` erforderlich. Fehlt diese Zusatzberechtigung, bleibt der Menüpunkt deaktiviert und ein direkter Aufruf des Rechnungstools wird serverseitig mit HTTP 403 verweigert. Im Rechnungstool wird der über Windows-SSO erkannte Benutzer angezeigt.

Während der Migration gilt: Berechnungslogik wird zuerst im Test- bzw. Vorschaumodus umgesetzt. Solange ein Teil noch nicht produktiv freigegeben ist, werden keine echten Rechnungen erzeugt und keine abrechnungsrelevanten Daten verändert.

Produktive Schreibvorgänge werden erst freigeschaltet, nachdem die Ergebnisse der Webberechnung mit dem bisherigen Rechnungstool anhand derselben Aufträge geprüft wurden.

## 3. Geplanter Rechnungslauf

Der Rechnungslauf soll einen Zeitraum sowie optional eine Kundennummer oder Auftragsnummer berücksichtigen können. Ohne Einschränkung auf Kunde oder Auftrag muss vor einem vollständigen Lauf eine ausdrückliche Bestätigung erfolgen.

Die Auswahl der abzurechnenden Aufträge und Positionen orientiert sich am bestätigten Verhalten des bisherigen Rechnungstools. Dazu zählen insbesondere Fakturierungsbeginn, Fakturierungsende, Stornierungen, eingefrorene Aufträge und die jeweilige Berechnungsart der Position.

## 4. Vorschau und Testlauf

Der erste Migrationsschritt ist ein rein lesender Rechnungslauf. Er zeigt für einen ausgewählten Auftrag die ermittelten Positionen, Berechnungszeiträume, Mengen, Preise, Rabatte, Steuersätze und Summen an, ohne produktive Buchungen auszuführen.

Der Testlauf dient als Vergleichsebene zwischen Alttool und Webanwendung. Abweichungen müssen vor einer produktiven Freigabe geklärt werden.

## 5. Aktueller Stand und noch nicht produktiv freigegeben

Der geschützte Einstieg in Phase 1 ist umgesetzt. Die Seite kennzeichnet den Bereich ausdrücklich als **Vorschau / Testlauf** und führt noch keine produktiven Fakturierungsaktionen aus.

Aktuell sind die eigentliche Berechnungsoberfläche, Rechnungserzeugung, Rechnungsnummernvergabe, PDF-Erzeugung, SEPA-Dateien, E-Mail-Versand, XRechnung und Mahnwesen noch nicht als Web-Rechnungstool freigegeben.

Dieses Kapitel wird bei jeder umgesetzten Funktion ergänzt, damit das Bedienhandbuch stets dem tatsächlich freigegebenen Funktionsstand entspricht.

## 6. Dokumentation und SQL

Technische Details des Rechnungstools werden in der allgemeinen **Technischen Doku** gepflegt. Alle bestätigten SQL-Abfragen und Berechtigungs-Statements des Rechnungstools werden im bestehenden **SQL-Statement-Wiki** ergänzt. Dieses Handbuch enthält dagegen nur den tatsächlichen Bedienablauf des Rechnungstools.
