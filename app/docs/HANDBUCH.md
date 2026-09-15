# Benutzerhandbuch – tops.net Buchhaltung

Stand: 15.09.2026

## 1. Zweck

Dieses Handbuch beschreibt die Bedienung der neuen Webanwendung während und nach der Ablösung der bisherigen Access-Oberfläche.

Die Anwendung bietet zwei Darstellungen:
- **Classic**: bewusst nahe an der bisherigen Access-Oberfläche
- **Modern**: übersichtlichere Webdarstellung mit denselben fachlichen Funktionen

Zwischen beiden Ansichten kann während der Arbeit gewechselt werden.

## 2. Mehrfenster-Arbeitsbereich

Vom Hauptmenü aus können mehrere fachlich zusammenhängende Ansichten gleichzeitig in schwebenden Fenstern geöffnet werden. Das ist besonders hilfreich beim Vergleichen oder Kopieren von Daten.

Fenster können:
- verschoben werden
- in der Größe verändert werden
- minimiert und maximiert werden
- geschlossen werden
- über die Taskleiste wieder in den Vordergrund geholt werden

Position und Zustand offener Fenster werden im Browser gespeichert und nach einem Reload wiederhergestellt.

## 3. Kundenverwaltung

Über die Kundenverwaltung können Kunden gesucht, geöffnet, neu angelegt und bearbeitet werden.

Zum Kunden gehören unter anderem:
- Stammdaten
- Ansprechpartner
- Branchen
- Rechnungsanschriften
- offene Rechnungen
- Aufträge

Wird ein neuer Kunde ohne Rechnungsanschrift angelegt, erzeugt die Anwendung automatisch eine Standard-Rechnungsanschrift aus den Kundendaten. Bestehende Bankdaten können dabei entsprechend der bisherigen Access-Logik übernommen werden.

## 4. Auftragsverwaltung

Aufträge können global gesucht oder aus einem Kunden heraus geöffnet werden.

In einem Auftrag stehen derzeit zur Verfügung:
- Auftragskopf anzeigen und bearbeiten
- Rechnungsanschrift und Zahlungsbedingung
- Rechnungsoptionen und Skonto
- Auftragspositionen
- Ticket-Mail vorbereiten

Die ID einer Auftragsposition ist anklickbar und öffnet die Position. Die zugehörigen Anbindungen werden getrennt über die Aktion **Anbindungen** geöffnet.

## 5. Auftragspositionen

Bei einer Auftragsposition können unter anderem Menge, Beschreibung, Preis, Fakturierungszeitraum, Rabatt, Mindestlaufzeit und Wiedervorlage gepflegt werden.

Hilfsbuttons:
- Mindestlaufzeit: `Jetzt`, `+1J`, `keine`
- Wiedervorlage: `-90T`, `-40T`, `keine`

Historische Datensätze verwenden für „keine“ teilweise den Platzhalter `01.01.1980`.

Bei Auswahl eines Produkts können Produktinformationen wie Beschreibung, Preis und weitere technische Abrechnungswerte übernommen werden.

## 6. Anbindungen

Anbindungen gehören zu einer Auftragsposition. Die Webanwendung zeigt die zugehörigen Accounting-Verknüpfungen und deren Referenzinformationen an.

Unterstützte Typen für neue Anbindungen:
- Netz-Accounting
- Port-Accounting
- Dialin-Accounting
- Dialin-Zeitabrechnung
- Domain-Accounting

Fremdaccounting steht als lesende Monatsauswertung zur Verfügung. Monat und Jahr werden ausgewählt; angezeigt werden Anbindung, Kunde, Auftrag/Position, MB In, MB Out, Gesamt und Rechnungsinfo. SMS-Verknüpfungen werden ausschließlich als Altbestand angezeigt und können nicht neu angelegt oder geändert werden.

Bei Netz, Port, Dialin und Domain kann die Referenz über Vorschlagslisten anhand verständlicher Bezeichnungen gesucht werden; die technische ID bleibt dabei sichtbar und wird weiterhin gespeichert.

## 7. Ticket erstellen

Im Auftragsdetail kann über **Ticket erstellen** eine vorbereitete Nachricht für den Helpdesk geöffnet werden. Sie enthält die wichtigsten Auftragsdaten und die zugehörigen Positionen.

## 8. Aufträge-Wiedervorlage

Über **Aufträge-Wiedervorlage** im Hauptmenü steht eine zentrale Liste der markierten Aufträge zur Verfügung. Ein Auftrag erscheint, wenn mindestens eine seiner Positionen in `txtInfo` mit `WV` gekennzeichnet ist. Die Liste kann nach Kunde, Auftragsnummer und Beschreibung durchsucht werden. Die Auftragsnummer ist direkt anklickbar und öffnet den normalen Auftrag mit allen Positionen.

## 9. Rechnungen

Über **Rechnungen** im Hauptmenü steht eine globale Rechnungsübersicht zur Verfügung. Sie kann nach Rechnungsnummer, Auftragsnummer oder Kunde durchsucht und nach **Alle**, **Offen** oder **Bezahlt** gefiltert werden.

Ein Klick auf die Rechnungsnummer öffnet das Rechnungsdetail. Dort werden unter anderem Rechnungs-ID und -nummer, Auftrag und Kunde, Rechnungs-/Versand-/Fälligkeits-/Bezahldatum, Zahlungsbedingung, Rechnungsbeträge, Ratenzahlung, Gutschrift, Verzugszinsen, Skonto-Werte, Mahnstufen, Mahngebühren, Sperrungen, Verlustabschreibung sowie Angaben zu strittigen Rechnungen angezeigt. Ist die gespeicherte Rechnungsdatei auf der Serverfreigabe erreichbar, kann sie über **Rechnung öffnen** direkt aus der Webanwendung aufgerufen werden; der UNC-Pfad kann zusätzlich kopiert werden. Die Rechnungsverwaltung ist bewusst lesend; Buchungs- oder Zahlungsdaten werden durch diese Webmaske nicht verändert.

Beim Kunden können weiterhin die offenen Rechnungen angezeigt werden. Von dort kann die einzelne Rechnung ebenfalls geöffnet werden.

## 10. Fremdaccounting

Über **Fremdaccounting** im Hauptmenü wird die Monatsauswertung der Fremd-Accountings geöffnet. Zuerst Monat und Jahr auswählen und **Anzeigen** klicken. Die Ansicht ist bewusst nur lesend. Soweit eine Zuordnung vorhanden ist, führen Auftrag und Position direkt zu den zugehörigen Datensätzen.

Hinweis zum Arbeitsbereich: Navigationsziele wie Kunden, Aufträge, Rechnungen, Wiedervorlagen und Fremdaccounting öffnen sich weiterhin in separaten verschiebbaren Fenstern innerhalb der Anwendung.

## 11. DATEV und Bilanzen

Über **DATEV und Bilanzen** im Hauptmenü stehen die Access-Auswertungen **Rechnungen ausführlich**, **Rechnungen kurz**, **Produkte**, **Kunden**, **Kunden ohne DATEV-Nummer** und **Alle Kundenkonten** zur Verfügung. Die ersten vier werden über einen Von-/Bis-Zeitraum ausgewertet. Fehlende DATEV-Kundenkonten oder Produktkontierungen werden deutlich hervorgehoben. **Kunden ohne DATEV-Nummer** zeigt Kunden mit Papieraufträgen, für die kein DATEV-Kundenkonto hinterlegt ist; **Alle Kundenkonten** listet die vorhandenen DATEV-Konten. Alle Ansichten sind nur lesend.

## 12. Rechnungslauf-Export

Über **Export Rechnungslauf (XLSX)** im Hauptmenü wird zunächst ein Start- und Enddatum gewählt. Anschließend erzeugt die Anwendung eine XLSX-Datei mit den Rechnungen dieses Zeitraums. Enthalten sind unter anderem Rechnungsdatum und -nummer, Kunde, Auftragsbeschreibung, Betrag, Fälligkeit, Zahlungsart, Lastschrifteinzug, Bezahldatum, Zahlbetrag, Kommentar, Rechnungspfad und gegebenenfalls das Datum der Forderungsausfall-Abschreibung. Bei Lastschrift wird wie im bisherigen Access-Export der Skonto-1-Rechnungsbetrag verwendet, sofern er positiv ist. Der Export verändert keine Daten.

## 13. Lastschriften bezahlt markieren

Über **Lastschriften bezahlt markieren** im Hauptmenü wird eine Vorschau der offenen Lastschriften geöffnet. Standardmäßig reicht der Fälligkeitszeitraum von heute minus 30 Tagen bis heute und kann über **Fällig ab** und **Fällig bis** angepasst werden. Angezeigt werden Anzahl, Gesamtsumme und die einzelnen Rechnungen mit dem tatsächlich zu buchenden Zahlbetrag.

Mit **Alle angezeigten Lastschriften als bezahlt markieren** werden nach einer Sicherheitsabfrage alle zu diesem Zeitpunkt noch offenen Lastschriften im gewählten Zeitraum verarbeitet. Das Bezahldatum wird auf die jeweilige Fälligkeit gesetzt. Bei vorhandenen Skontostufen wird dieselbe Reihenfolge wie in Access verwendet: Skonto 1, danach 2, danach 3; die zuletzt gültige Stufe bestimmt den Zahlbetrag. Die Aktion verändert Zahlungsdaten und sollte deshalb erst nach Kontrolle von Zeitraum, Anzahl und Gesamtsumme ausgeführt werden.

## 14. Rechnungen ohne USt.

Über **Rechnungen ohne USt...** im Hauptmenü kann ein Startdatum eingegeben werden. Angezeigt werden alle Rechnungen ab diesem Datum, bei denen der Betrag positiv und der Steuerbetrag 0 ist. Die Liste zeigt Rechnungsnummer, Rechnungsdatum, Kunde, Betrag, Rechnungsbetrag und Steuer. Die Funktion ist ausschließlich lesend.


## 15. Zugeordnete Branchen

Über **Zugeordnete Branchen** wird eine nach Branche gruppierte Kundenübersicht geöffnet. Je Zuordnung werden Kundennummer, Kundenname, Adresse und Telefon angezeigt; die Kundennummer führt direkt zur Kundenansicht. Zusätzlich kann dieselbe Branchenzuordnung über **Branchen als XLSX exportieren** als moderne Excel-Datei ausgegeben werden. Der Export enthält Branche, Kunden-ID, Kundenname, Telefax und Branchencode. Beide Funktionen verändern keine Daten.

## 16. Accounting-Berichte

Im Bereich **Allgemein / Accounting** stehen die drei Auswertungen **Accountings ohne Zusatzinfos**, **Accountings mit Zusatzinfos und Zusatzsumme** und **Accountings mit Zusatzinfos ohne Zusatzsumme** zur Verfügung. Für jede Auswertung werden Kundennummer, Monat und Jahr angegeben. Angezeigt werden die Accounting-Einträge mit MB In, MB Out, Gesamt-MB und Rechnungsinfo.

Die beiden Varianten mit Zusatzinfos zeigen außerdem Hinweise auf abweichende Start-/Enddaten eines Dienstes. Bei Dialin-Accounting (Typ 3) wird die im gewählten Monat aufsummierte Verbindungszeit als Stunden:Minuten:Sekunden sowie als Sekundenwert ausgegeben. **Mit Zusatzsumme** summiert alle angezeigten Werte, **ohne Zusatzsumme** nur die abrechenbaren Datensätze. Die Berichte verändern keine Daten.

## 17. Noch nicht vollständig migrierte Bereiche

Folgende Bereiche werden schrittweise ergänzt und deshalb in diesem Handbuch erst nach ihrer Umsetzung vollständig beschrieben:
- Rechnungstool
- SMS-Zugänge
- technische Pflege von Netzen, Ports und Dialins
- eigentliche Domainverwaltung aus der separaten Datenbank `domains`

## 18. Dokumentationsstand

Im Classic-Hauptmenü stehen links drei Direktbuttons für **Technische Doku**, **Benutzerhandbuch** und **SQL-Statement-Wiki** bereit. Im modernen Frontend stehen dieselben drei Direktbuttons oben rechts. Alle drei öffnen die jeweilige Dokumentation in einem neuen Browser-Tab.

Dieses Handbuch wird parallel zur Entwicklung fortgeschrieben. Neue Funktionen oder geänderte Abläufe sollen im selben Arbeitsschritt auch hier dokumentiert werden. Zusätzlich steht im Hauptmenü ein **SQL-Statement-Wiki** zur Verfügung. Es enthält die bestätigten SQL-Abfragen und Rechte-Statements des Migrationsprojekts jeweils mit kurzer Erklärung. Dokumentation, Handbuch und SQL-Wiki besitzen oben ein Suchfeld. Während der Eingabe werden alle Fundstellen markiert; mit den Pfeiltasten neben dem Suchfeld oder mit Enter kann zwischen Treffern gewechselt werden.
