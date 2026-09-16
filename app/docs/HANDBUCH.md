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

Die drei historischen Access-Reports **Accountings ohne Zusatzinfos**, **Accountings mit Zusatzinfos und Zusatzsumme** und **Accountings mit Zusatzinfos ohne Zusatzsumme** sind technisch in der Webanwendung nachgebildet. Da in der aktuellen Access-Hauptmaske keine sichtbaren Buttons für diese drei Reports vorhanden sind, werden sie auch im Web-Hauptmenü nicht als eigene Menüpunkte angeboten. Für eine spätere Einordnung bleibt die Funktion unter `/accounting-berichte` erhalten. Die Auswertungen verwenden Kundennummer, Monat und Jahr und zeigen die Accounting-Einträge mit MB In, MB Out, Gesamt-MB und Rechnungsinfo.

Die beiden Varianten mit Zusatzinfos zeigen außerdem Hinweise auf abweichende Start-/Enddaten eines Dienstes. Bei Dialin-Accounting (Typ 3) wird die im gewählten Monat aufsummierte Verbindungszeit als Stunden:Minuten:Sekunden sowie als Sekundenwert ausgegeben. **Mit Zusatzsumme** summiert alle angezeigten Werte, **ohne Zusatzsumme** nur die abrechenbaren Datensätze. Die Berichte verändern keine Daten.


## 17. Produkte pflegen

Über **Produkte pflegen** wird die Produktliste geöffnet. Produkte können nach ID, Kürzel oder Beschreibung gesucht werden. Ein Klick auf ID oder Kürzel öffnet das Produkt zur Bearbeitung; **Neues Produkt** legt einen neuen Datensatz an. Ein Löschen von Produkten ist im Web nicht vorgesehen.

Im Produktformular werden Kürzel, Beschreibung, Nettopreis, Steuer, Abrechnungsart, Mengenschlüssel, DATEV-Auswahl, Produktgruppe, Staffeltyp, maximale Rabatte sowie die Kennzeichen **Accountingabhängig?** und **Produkt veraltet?** gepflegt. Abhängig vom Staffeltyp erscheint die passende Staffel-, Tarif- oder Konditionsauswahl. Beim Wechsel auf **Domain-Accounting** wird der Mengenschlüssel wie im alten Access-Formular auf **Stück(e)** gesetzt. Vorhandene Datensätze werden beim bloßen Öffnen nicht verändert.

Die eigentliche Pflege von Domain-Konditionen und weiteren Tarifarten bleibt separat. Staffelgruppen für Abrechnungsart #1 sind inzwischen migriert.

## 18. Staffelgruppen (#1)

Über **Abrechnungsart #1: Staffelgruppen bearbeiten** wird die Staffelgruppenpflege geöffnet. Jede Staffelgruppe besitzt Bezeichnung und Abrechnungseinheit. Die zugehörigen Preisstufen werden direkt darunter nach Menge sortiert angezeigt und können angelegt oder bearbeitet werden. Löschen wird im Web bewusst nicht angeboten.

Der **Staffelrechner** erzeugt neue Preisstufen aus Startwert, Endwert, Schrittweite, Startpreis und Schrittpreis. Wie im bestätigten Access-VBA werden vor jeder erzeugten Zeile zunächst Schrittweite und Schrittpreis addiert. Die erzeugten Zeilen werden zusätzlich zu vorhandenen Preisstufen angelegt.


## 19. Linearstaffeln (#2)

Über **Abrechnungsart #2: Linear-Staffeln bearbeiten** wird die Pflege der linearen Staffeln geöffnet. Eine Linearstaffel enthält Menge frei, Preis pro Einheit, Bezeichnung, Basispreis und Abrechnungseinheit. Bestehende Staffeln können bearbeitet und über **Neue Staffel** neue Datensätze angelegt werden. Löschen wird im Web nicht angeboten.

Die Felder entsprechen direkt `tblLinearStaffel`: `intMengeFrei`, `floatPreisEinheit`, `strBezeichnung`, `floatBasisPreis` und `strAbrechnungseinheit`. Neue Datensätze erhalten automatisch eine neue `rowguid`.


## 20. Zeittarife (#3)

Über **Abrechnungsart #3: Zeittarife bearbeiten** wird die Pflege der Zeitabrechnung geöffnet. Ein Tarif besitzt Tarifname, freie Sekunden, Mindestabnahmesekunden und Taktung in Sekunden. Die zugehörigen Zeitfenster werden darunter angezeigt und können angelegt oder bearbeitet werden. Jedes Zeitfenster besitzt Beginn, Ende und Minutenpreis.

Die Access-Verknüpfung wird unverändert abgebildet: `tblZeittarife.intID` ist mit `tblZeittarifeZonen.intTarifID` verknüpft. Access speichert reine Uhrzeiten technisch mit dem historischen Datum 30.12.1899; die Weboberfläche zeigt dafür nur die Uhrzeit einschließlich Sekunden. Löschen wird im Web bewusst nicht angeboten.

## 21. Bereichsstaffeln (#6)

Über **Abrechnungsart #6: Bereichsstaffeln bearbeiten** wird die Pflege der Bereichsstaffeln geöffnet. Eine Bereichsstaffel besitzt Bezeichnung und Abrechnungseinheit. Die zugehörigen Bereiche werden darunter angezeigt und können angelegt oder bearbeitet werden.

Je Bereich werden Grundgebühr, Bereichs-Grundgebühr, Stückpreis sowie **Menge ab** und **Menge bis** gepflegt. Die Access-Verknüpfung wird unverändert abgebildet: `tblBereichsStaffel.intID` ist mit `tblBereichsStaffelPreise.intStaffelGruppenID` verknüpft. Löschen wird im Web derzeit nicht angeboten.

## 22. Bandbreiten-Tarife (#4/#7)

Über **Abrechnungsart #4/#7: Bandbreiten-Tarife bearbeiten (MAX oder SUM)** wird die Pflege der Bandbreitenstaffeln geöffnet. Ein Tarif besitzt eine Tarifnummer und einen Tarifnamen. Die zugehörigen Preiszeilen werden nach Bandbreite sortiert darunter angezeigt und können angelegt oder bearbeitet werden.

Je Preiszeile werden `intMenge` als **kBit / Sekunde** und `fVkPreis` als **Nettopreis** gepflegt. Die Access-Verknüpfung wird unverändert abgebildet: `tblBandbreiteStaffel.intID` ist mit `tblBandbreiteStaffelPreise.intStaffelGruppenID` verknüpft. Die sichtbare Access-Schaltfläche **Automatisch berechnen…** besitzt am bestätigten Formular keine Ereignisprozedur und wird deshalb nicht mit erfundener Logik nachgebaut. Löschen wird im Web derzeit nicht angeboten.

## 23. Domainkonditionen (#5)

Über **Abrechnungsart #5: Domainkonditionen bearbeiten** wird die Pflege der Domainkonditionen aus der separaten Datenbank `domains` geöffnet. Gepflegt werden Name, Abrechnungsintervall, Intervallpreis, Einrichtungsinformationen und die Verfügbarkeit für neue Aufträge. Löschen wird im Web bewusst nicht angeboten.

Das reguläre Abrechnungsintervall besteht aus Anzahl plus Einheit: 4=Tage, 5=Wochen, 6=Monate, 7=Jahre. Beim im Einrichtungspreis enthaltenen Zeitraum verwendet Access dagegen die abweichende Zuordnung 4=Tage, 5=Monate, 6=Wochen, 7=Jahre. Diese historische Abweichung wird im Web exakt beibehalten. Der in Access gespeicherte Filter `strKonditionsName Like "*prime*"` wird nicht automatisch erzwungen, da das bestätigte Formular sichtbar auch nicht passende Datensätze anzeigt.

## 24. SMS-Zugänge

Über **SMS-Zugänge pflegen** wird die Verwaltung der GeneralWireless-Zugänge aus `tblSMSZugaenge` geöffnet. Angezeigt und bearbeitet werden SMS-Accountnummer, Kundennummer, Bemerkung und Rechnungsinfo sowie die Felder für Corporate- und Single-Accounts. Der Kontotyp entspricht `boolIsCustomerAccount`: `0` = Single Account, `1` = Corporate Account.

Kennwörter werden aus Sicherheitsgründen nicht aus der Datenbank in das Webformular zurückgelesen. Bei bestehenden Datensätzen bedeutet ein leeres Kennwortfeld **unverändert**; nur eine neue Eingabe überschreibt das vorhandene Kennwort. `datErstelltAm` und `strErstelltVon` sind im Web nicht nachträglich editierbar; neue Datensätze erhalten den aktuellen Zeitpunkt und den technischen Ersteller `webapp`. Löschen wird nicht angeboten.

## 25. Anbindungen / Verbindungen

Über **Verbindungen pflegen** wird im Classic-Frontend direkt die Access-artige Einzelmaske geöffnet. Es ist keine vorgelagerte Auswahlliste mehr nötig. Unten kann wie in Access durch die Datensätze geblättert werden; der Zähler zeigt die aktuelle Position und die Trefferzahl. Die Datensatznavigation bleibt dabei im bereits geöffneten Fenster; beim Vor-/Zurückblättern wird kein neues Workspace-Fenster erzeugt. Eine Anbindung verbindet eine Auftragsposition mit genau einer technischen Referenz.

Die Accounting-Arten entsprechen Access: 1 Ip-NetzAccounting, 2 Port-Accounting, 3 Dialin-Accounting, 4 Fremd-Accounting, 5 Zeit-Accounting, 6 Domainen-Accounting und 7 SMS-Accounting. Die technischen Referenzdaten werden im Anbindungsformular nur angezeigt. Bei Fremd-Accounting werden die zugehörigen Auswertungszeilen über die Anbindungs-ID dargestellt. Dialin-Kennwörter und SNMP-Community werden nicht angezeigt. Löschen ist nicht vorgesehen.

Die klassische Anbindungsmaske folgt dem Access-Aufbau mit Anbindungs-ID, Accounting-Informationen, typabhängigem read-only Unterformular, Abrechnungsdaten, Rechnungsinfo, Kunden- und Auftragsinformationen sowie den Schaltflächen Neue Anbindung, Fremdaccountings und Schliessen. Zusätzliche Querverknüpfungen zu den technischen Pflegeformularen werden bewusst nur im Modern-Frontend angeboten, damit die Classic-Ansicht möglichst eng am Access-Verhalten bleibt. Beim Öffnen einer Anbindung aus der modernen Verbindungsübersicht wird die moderne Detailansicht ausdrücklich beibehalten; ältere noch offene Classic-Fenster im Workspace können diese Auswahl nicht mehr überlagern. Das Anbindungsfenster öffnet bewusst größer, damit die vollständige Classic-Maske ohne unnötigen internen Scrollbalken sichtbar bleibt. Graue, nicht editierbare Felder können per Rechtsklick oder Doppelklick gefiltert werden. Angeboten werden Gleich, Nicht gleich, Beginnt mit, Beginnt nicht mit, Enthält, Enthält nicht, Endet mit und Endet nicht mit sowie Alle Filter entfernen. Auch die sichtbaren technischen Referenzfelder sind in die Feldsuche einbezogen.

## 26. Netze

Über **Netze pflegen** wird die Pflege der IP-Netze aus `tblAnbindungNetze` geöffnet. Die Classic-Ansicht orientiert sich direkt am Access-Formular `frmNetze`: Netzwerk, Netzmaske, GatewayRouter, KundenNetz, AccountingEingerichtet, InUse, Verwendung, Bemerkung, Standort und Rechnungsinfo werden in einer Einzelmaske gepflegt. **Neues Netz** legt einen neuen Datensatz an, **Schliessen** kehrt zum Hauptmenü zurück; eine Löschfunktion gibt es nicht.

Die Classic-Ansicht besitzt wie Access eine Datensatznavigation und eine Suche. Beim Blättern mit erster/zurück/weiter/letzter Datensatz bleibt dieselbe Fenstermaske geöffnet; es wird kein neues Workspace-Fenster erzeugt. Die Modern-Ansicht zeigt zunächst eine Liste und öffnet Datensätze zur Bearbeitung. Die historischen Checkboxfelder heißen in SQL `intKundenNetz`, `intAccountingEingerichtet` und `intInUse`; das Web behandelt jeden Wert ungleich 0 als aktiv und schreibt beim Speichern `-1` bzw. `0`.

## 27. Ports

Über **Ports pflegen** wird die Port-Accounting-Pflege aus `tblPort` geöffnet. Die Classic-Ansicht folgt `frmPorts` mit Router-IP, SNMP-Community, MIB-Variablen, Portbeschreibung, Rechnungsinfo, OverrunLimit und dem Hinweisblock zur ifDescr-Prüfung. **Neuer Port** öffnet einen neuen Datensatz, **Schliessen** kehrt zum Hauptmenü zurück; Löschen wird nicht angeboten.

Beim Anlegen stehen wie in Access Eingabehilfen für Portbeschreibung und SNMP-Community zur Verfügung. Die SNMP-Werte werden dabei nicht in Quellcode oder Dokumentation fest verdrahtet, sondern aus vorhandenen Portdaten geladen. IST-Wert und Zeitpunkt des letzten ifDescr-Auslesens sind nur Anzeige; SOLL-Wert und MIB-Angabe können gepflegt werden. Die Datensatznavigation bleibt beim Blättern im selben Workspace-Fenster.

## 28. Dialins

Über **Dialins pflegen** wird die Radius-/Dialin-Pflege aus `tblAnbindungDialin` geöffnet. Die Classic-Ansicht orientiert sich an `frmAnbindungDialin` mit Login, Rechnungsinfo, InternetProf.-Kennzeichen, IP, Deaktivierung, Radius-Eigenschaften und dem Unterbereich für Einwahlnummern. Der Button **Heutiges Datum einfügen** setzt wie Access das Deaktivierungsdatum auf das aktuelle Datum.

Kennwörter werden aus Sicherheitsgründen niemals aus der Datenbank gelesen oder im Formular angezeigt. Bei bestehenden Datensätzen bedeutet ein leeres Kennwortfeld **unverändert**; nur eine neue Eingabe überschreibt das Kennwort. Beim Anlegen ist ein Kennwort erforderlich. Die Einwahlnummern werden über `tblAnbindungDialinEinwahlnummern.intDID -> tblAnbindungDialin.intID` zugeordnet. Für die aus Access bestätigte ID 1 wird die Anzeige 9598100 verwendet; weitere ID-zu-Rufnummer-Zuordnungen werden erst nach bestätigter Lookup-Quelle beschriftet. Löschen wird weder für Dialins noch für Einwahlnummer-Zuordnungen angeboten. Die Classic-Datensatznavigation bleibt im selben Workspace-Fenster. Das Dialin-Fenster verwendet die normale Breite, öffnet aber höher als die Standardfenster, damit Hauptformular und Einwahlnummern ohne unnötiges Scrollen besser sichtbar sind.

## 29. Domain-Einträge

Über **Domain-Einträge bearbeiten** wird die DNS-Zonenpflege aus `domains.dbo.tblAllgemeineDomain` und `domains.dbo.tblDomainEintraege` geöffnet. Die Classic-Ansicht bildet die Access-Maske mit Allgemeiner Domain-ID, Domainname, Klartextname, Domain-Typ und den DNS-Einträgen Name, Typ und Adresse nach. Hostnamen ohne abschließenden Punkt werden wie in Access relativ zum Domainnamen interpretiert.

Einzelne DNS-Einträge können angelegt, bearbeitet und nach Sicherheitsabfrage gelöscht werden. Das Löschen einer kompletten Domain ist ausdrücklich **nicht** Bestandteil dieser Maske. Die historische Access-Automatik für SOA-Serial und das anschließende Neueinlesen der Zone auf den DNS-Servern ist noch nicht aktiviert; die Oberfläche weist nach Änderungen darauf hin.

## 30. Domain eintragen

Über **Domain eintragen** wird eine neue Domain mit Domainname (ACE), Klartextname, Kundennummer und optionalem Auth Code angelegt. Die Kundennummer wird vor dem Speichern geprüft; bereits vorhandene Domains werden abgewiesen.

Beim Anlegen erzeugt die Anwendung automatisch die allgemeine Domain-ID sowie die drei Standard-DNS-Einträge SOA, NS1 und NS2. Für neue DNS-Einträge wird der Standard-TTL 3600 verwendet. Anders als im alten Access-Formular ist kein zweites Anlegen pro DNS-Zeile notwendig. Nach erfolgreicher Anlage öffnet sich direkt **Domain-Einträge bearbeiten** für die neue Zone. DNSSEC und das historische Outlook-basierte DNS-Neueinlesen sind hier bewusst nicht enthalten.

In den aktiven technischen Masken für Netze, Ports, Dialins, Domain-Einträge und Domain eintragen steht oben außerdem ein direkter Wechsel zwischen klassischer und moderner Ansicht zur Verfügung.



## 31. IPv4 Reverse

Über **IPv4 Reverse** kann die bestehende Zuordnung einzelner IPv4-Adressen zu Kundennummern eingesehen werden. Die Ansicht ist bewusst nur lesend, weil in der alten Access-Maske keine bestätigte Speicherlogik vorhanden ist. Gesucht werden kann nach Kundennummer und zusätzlich nach einer vollständigen oder teilweise eingegebenen IPv4-Adresse.

Der historisch im Access-Formular gespeicherte Filter auf `intIPbyte3 = 185` wird in der Webansicht nicht automatisch angewendet. Dadurch lassen sich alle vorhandenen Zuordnungen einsehen.

## 32. Bewusst deaktivierte Domain-Funktionen

Die alten Access-Schaltflächen **Handles pflegen**, **Owner pflegen** und **Look up starten** bleiben im Web-Frontend deaktiviert. Diese Funktionen wurden im bisherigen Arbeitsablauf nicht genutzt und werden deshalb nicht separat migriert.

Sollte dafür später wieder Bedarf entstehen, ist die vorgesehene Stelle die geplante zentrale Domainverwaltung über die DENIC-API. Die alte Access-Logik wird dafür nicht als neue Webfunktion nachgebaut.

## 33. Noch nicht vollständig migrierte Bereiche

Folgende Bereiche werden schrittweise ergänzt und deshalb in diesem Handbuch erst nach ihrer Umsetzung vollständig beschrieben:
- Rechnungstool
- eigentliche Domainverwaltung aus der separaten Datenbank `domains`

## 34. Dokumentationsstand

Im Classic-Hauptmenü stehen links drei Direktbuttons für **Technische Doku**, **Benutzerhandbuch** und **SQL-Statement-Wiki** bereit. Im modernen Frontend stehen dieselben drei Direktbuttons oben rechts. Alle drei öffnen die jeweilige Dokumentation in einem neuen Browser-Tab.

Dieses Handbuch wird parallel zur Entwicklung fortgeschrieben. Neue Funktionen oder geänderte Abläufe sollen im selben Arbeitsschritt auch hier dokumentiert werden. Zusätzlich steht im Hauptmenü ein **SQL-Statement-Wiki** zur Verfügung. Es enthält die bestätigten SQL-Abfragen und Rechte-Statements des Migrationsprojekts jeweils mit kurzer Erklärung. Dokumentation, Handbuch und SQL-Wiki besitzen oben ein Suchfeld. Während der Eingabe werden alle Fundstellen markiert; mit den Pfeiltasten neben dem Suchfeld oder mit Enter kann zwischen Treffern gewechselt werden.
