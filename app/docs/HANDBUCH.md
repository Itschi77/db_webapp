# Benutzerhandbuch – tops.net Buchhaltung

Stand: 14.09.2026

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

Fremdaccounting und SMS-Verknüpfungen können als Altbestand angezeigt werden, werden derzeit aber nicht neu angelegt.

## 7. Ticket erstellen

Im Auftragsdetail kann über **Ticket erstellen** eine vorbereitete Nachricht für den Helpdesk geöffnet werden. Sie enthält die wichtigsten Auftragsdaten und die zugehörigen Positionen.

## 8. Offene Rechnungen

Beim Kunden können offene Rechnungen angezeigt werden. Die Ansicht orientiert sich an der bisherigen Access-Abfrage und ist derzeit lesend.

## 9. Noch nicht vollständig migrierte Bereiche

Folgende Bereiche werden schrittweise ergänzt und deshalb in diesem Handbuch erst nach ihrer Umsetzung vollständig beschrieben:
- Aufträge-Wiedervorlage
- vollständige Rechnungsverwaltung
- DATEV und Bilanzen
- Fremdaccounting
- SMS-Zugänge
- technische Pflege von Netzen, Ports und Dialins
- eigentliche Domainverwaltung aus der separaten Datenbank `domains`

## 10. Dokumentationsstand

Dieses Handbuch wird parallel zur Entwicklung fortgeschrieben. Neue Funktionen oder geänderte Abläufe sollen im selben Arbeitsschritt auch hier dokumentiert werden.
