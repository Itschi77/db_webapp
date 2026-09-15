# SQL-Statement-Wiki – tops.net Buchhaltung

Stand: 15.09.2026

Dieses Wiki sammelt die im Zuge der Access-Migration bestätigten SQL-Abfragen und administrativen SQL-Statements. Zu jedem Statement steht kurz dabei, wofür es verwendet wird. Zugangsdaten, Kennwörter und andere Secrets werden hier ausdrücklich nicht dokumentiert.

## 1. Offene Rechnungen

**Zweck:** Liefert alle noch nicht bezahlten Rechnungen und verbindet sie mit Auftrag und Kunde. In Access dient diese Abfrage als Grundlage der offenen Rechnungen eines Kunden.

```sql
SELECT tblKunde.strName, tblRechnung.*, tblRechnung.boolBezahlt, tblKunde.intID
FROM tblKunde
INNER JOIN (tblRechnung
    INNER JOIN tblAuftrag ON tblRechnung.intAufNr = tblAuftrag.intAufNr)
    ON tblKunde.intID = tblAuftrag.intKID
WHERE tblRechnung.boolBezahlt = False;
```

## 2. Aufträge-Wiedervorlage

**Zweck:** Zeigt Aufträge, deren mindestens eine Position in `txtInfo` mit `WV` markiert ist. Das Datum `datWiedervorlageVertrieb` ist für diese zentrale Access-Liste nicht das Auswahlkriterium.

```sql
SELECT DISTINCT tblAuftrag.*, tblKunde.*, tblAuftragPos.txtInfo
FROM (tblKunde RIGHT JOIN tblAuftrag
    ON tblKunde.intID = tblAuftrag.intKID)
INNER JOIN tblAuftragPos
    ON tblAuftrag.intAufNr = tblAuftragPos.intAufNr
WHERE tblAuftragPos.txtInfo Like "WV";
```

**Hinweis:** Die Access-Abfrage kann wegen `SELECT DISTINCT tblAuftrag.*` und des OLE-Feldes in `tblAuftrag` scheitern. Die Webanwendung bildet deshalb nur die fachliche Auswahl nach.

## 3. Fremdaccounting

**Zweck:** Monatsauswertung für Anbindungen vom Typ 4. Monat und Jahr werden als Parameter übergeben; die Mengen und Gesamtsumme stammen bereits aus `tblAnbindungAuswertung`.

```sql
SELECT tblAnbindungen.intTyp,
       tblAnbindungAuswertung.decMBin,
       tblAnbindungAuswertung.decMBout,
       tblAnbindungAuswertung.intMonat,
       tblAnbindungAuswertung.intJahr,
       tblAnbindungen.intKID,
       tblAnbindungen.intID,
       tblAnbindungAuswertung.strrechnungsinfo,
       tblAnbindungAuswertung.intAnbindungID,
       tblAnbindungAuswertung.decGesamt,
       tblAnbindungAuswertung.intID
FROM tblAnbindungen
INNER JOIN tblAnbindungAuswertung
    ON tblAnbindungen.intID = tblAnbindungAuswertung.intAnbindungID
WHERE tblAnbindungen.intTyp = 4
  AND tblAnbindungAuswertung.intMonat = [MONAT zweistellig]
  AND tblAnbindungAuswertung.intJahr = [JAHR vierstellig];
```

## 4. DATEV-Rechnungsinformationen

**Zweck:** Grundlage der DATEV-Berichte nach Rechnungen, Produkten und Kunden. Die Abfrage verbindet Rechnungen mit berechneten Auftragspositionen, Kundenkonto und DATEV-Produktkontierung und reduziert Netto/Steuer um den Positionsrabatt.

```sql
SELECT tblRechnung.intRechNr,
       tblRechnung.datRechnungsDatum,
       tblAuftragPosBerechnet.intAufPosID,
       IIf(IsNull([strdatevkundenkonto])=True,
           "SOFORT KUNDENKONTO NACHTRAGEN!",
           [strDatevKundenKonto]) AS DatevKunde,
       IIf(IsNull([strdatevkontierung])=True,
           "NICHT VORHANDEN",
           [strDatevkontierung]) AS DatevProdukt,
       [tblAuftragposberechnet.fBetrag]*(1-([frabattinprozent]/100)) AS fbetragR,
       [tblauftragposberechnet.fSteuern]*(1-([frabattinprozent]/100)) AS fSteuernR,
       IIf(IsNull([strdatevkontierung])=True,
           "SOFORT DATEV PRODUKT-KONTIERUNG NACHTRAGEN!",
           [stDatevBezeichnung]) AS DatevProduktBezeichnung,
       tblKunde.strName AS strKundenName,
       tblAuftragPosBerechnet.intKopieKundenID,
       tblAuftragPosBerechnet.strKopieBeschreibung AS AuftragPosBeschreibung,
       [Rechnungsdatum ab] AS Ausdr1,
       [Rechnungsdatum bis] AS Ausdr2
FROM tblAuftragPos
INNER JOIN ((((tblRechnung
INNER JOIN tblAuftragPosBerechnet
    ON tblRechnung.intID = tblAuftragPosBerechnet.intRechnungIntID)
LEFT JOIN tblAuftrag ON tblRechnung.intAufNr = tblAuftrag.intAufNr)
LEFT JOIN tblKunde ON tblAuftragPosBerechnet.intKopieKundenID = tblKunde.intID)
LEFT JOIN tblDatevBezeichnungen ON tblAuftragPosBerechnet.intDatevID = tblDatevBezeichnungen.intID)
    ON tblAuftragPos.intID = tblAuftragPosBerechnet.intAufPosID
WHERE tblRechnung.datRechnungsDatum >= [rechnungsdatum ab]
  AND tblRechnung.datRechnungsDatum <= [rechnungsdatum bis];
```

## 5. Kunden ohne DATEV-Konto

**Zweck:** Prüfliste für Kunden mit Papieraufträgen, aber ohne eingetragenes DATEV-Kundenkonto. Historische Access-Logik betrachtet nicht stornierte oder erst nach dem 31.12.2000 stornierte Aufträge.

```sql
SELECT tblKunde.intID,
       tblKunde.strName,
       IsNull([datStorniereAb])=True Or [datStorniereAb]>#12/31/2000# AS Ausdr1
FROM tblKunde
INNER JOIN tblAuftrag ON tblKunde.intID = tblAuftrag.intKID
GROUP BY tblKunde.intID,
         tblKunde.strName,
         IsNull([datStorniereAb])=True Or [datStorniereAb]>#12/31/2000#,
         tblAuftrag.boolPapierrechnung,
         IsNull([strdatevkundenkonto])
HAVING (IsNull([datStorniereAb])=True Or [datStorniereAb]>#12/31/2000#)<>0
   AND tblAuftrag.boolPapierrechnung<>0
   AND IsNull([strdatevkundenkonto])<>0;
```

## 6. Alle DATEV-Kundenkonten

**Zweck:** Listet alle Kunden, bei denen ein DATEV-Kundenkonto hinterlegt ist.

```sql
SELECT tblKunde.intID,
       tblKunde.strName,
       tblKunde.strDatevKundenKonto
FROM tblKunde
WHERE Len([strdatevkundenkonto]) > 0;
```

## 7. Rechnungslauf-Export

**Zweck:** Exportiert Rechnungen eines frei gewählten Rechnungszeitraums. Bei Lastschrift wird, sofern vorhanden, der Skonto-1-Rechnungsbetrag verwendet. Die Webanwendung gibt diese Daten als echte XLSX-Datei aus.

```sql
SELECT FormatDateTime([datRechnungsDatum],2) AS Rechnungsdatum,
       tblRechnung.intRechNr,
       tblRechnung.strKundenNameAufRechnung,
       tblRechnung.strKopieAuftragsbeschreibung,
       IIf([boolIstBankeinzug]=-1,
           IIf([fRechnungsbetragMitSkonto1]>0,[fRechnungsbetragMitSkonto1],[fRechnungsbetrag]),
           [fRechnungsbetrag]) AS Betrag,
       FormatDateTime([datFaelligkeitsDatum],2) AS Fälligkeit,
       IIf([boolIstBankeinzug]=-1,"LS","R") AS Zahlung,
       IIf([boolIstBankeinzug]=-1,FormatDateTime([datFaelligkeitsDatum],2),"") AS [LS Einzug am],
       FormatDateTime([datBezahlDatum],2) AS [Bezahlt am],
       tblRechnung.fBezahlterBetrag AS [Z-Betrag],
       tblRechnung.strZahlungskommentar AS Kommentar,
       "file:" & [strPfadZurRechnung] AS Rechnungspfad,
       FormatDateTime([datForderungsausfallAbgeschriebenAm],2) AS [Abgeschrieben am]
FROM tblZahlungsbedingung
INNER JOIN (tblRechnung
INNER JOIN tblAuftrag ON tblRechnung.intAufNr = tblAuftrag.intAufNr)
    ON tblZahlungsbedingung.intID = tblAuftrag.intZahlungsbedingungID
WHERE tblRechnung.datRechnungsDatum Between [Startdatum] And [Enddatum]
ORDER BY tblRechnung.intRechNr;
```

## 8. Offene Lastschriften (`AAAmyLastschriften`)

**Zweck:** Liefert unbezahlte Rechnungen aus Aufträgen mit Lastschrift-Zahlungsbedingung. Access berücksichtigt dabei die Zahlungsbedingungen 3 und 26.

```sql
SELECT tblRechnung.intID,
       tblRechnung.intAufNr AS tblRechnung_intAufNr,
       tblRechnung.datFaelligkeitsDatum,
       tblRechnung.boolBezahlt,
       tblRechnung.datBezahlDatum,
       tblRechnung.fBezahlterBetrag,
       tblRechnung.fRechnungsbetrag,
       tblRechnung.datSkonto1Bis,
       tblRechnung.datSkonto2Bis,
       tblRechnung.datSkonto3Bis,
       tblRechnung.fRechnungsbetragMitSkonto1,
       tblRechnung.fRechnungsbetragMitSkonto2,
       tblRechnung.fRechnungsbetragMitSkonto3,
       tblAuftrag.intAufNr AS tblAuftrag_intAufNr,
       tblAuftrag.intZahlungsbedingungID
FROM tblAuftrag
INNER JOIN tblRechnung ON tblAuftrag.intAufNr = tblRechnung.intAufNr
WHERE (tblRechnung.boolBezahlt<>-1 AND tblAuftrag.intZahlungsbedingungID=3)
   OR (tblRechnung.boolBezahlt<>-1 AND tblAuftrag.intZahlungsbedingungID=26)
ORDER BY tblRechnung.intID;
```

**Schreiblogik der Access-Maske:** Beim Sammelmarkieren werden `datBezahlDatum`, `fBezahlterBetrag` und `boolBezahlt` gesetzt. Als Bezahldatum wird die Fälligkeit verwendet. Gültige Skontostufen werden der Reihe nach geprüft; die zuletzt passende Stufe bestimmt den Zahlbetrag.

## 10. Rechnungen ohne Umsatzsteuer (`qRechnungenOhneSteuernAb`)

**Zweck:** Listet Rechnungen ab einem vom Benutzer eingegebenen Rechnungsdatum, deren `fBetrag` positiv ist und deren Steuerbetrag `fSteuer` genau 0 ist. Die Abfrage ist rein lesend.

```sql
SELECT tblRechnung.intRechNr,
       tblRechnung.datRechnungsDatum,
       tblRechnung.fBetrag,
       tblRechnung.fRechnungsbetrag,
       tblRechnung.fSteuer,
       tblRechnung.strKundenNameAufRechnung
FROM tblRechnung
WHERE tblRechnung.datRechnungsDatum >= [?]
  AND tblRechnung.fBetrag > 0
  AND tblRechnung.fSteuer = 0;
```

**Webversion:** Der anonyme Access-Parameter `[?]` wird durch ein explizites Datumsfeld **Rechnungsdatum ab** ersetzt. Die fachliche Filterlogik bleibt unverändert.

## 11. Berechtigungs-Statements für `janus_connect`

**Zweck:** Dokumentiert die im Migrationsprojekt bewusst vergebenen Minimalrechte. Die Statements enthalten keine Zugangsdaten.

```sql
GRANT SELECT ON dbo.tblAnbindungAuswertung TO janus_connect;
GRANT SELECT ON dbo.tblAuftragPosBerechnet TO janus_connect;
GRANT SELECT ON dbo.tblDatevBezeichnungen TO janus_connect;
GRANT SELECT ON dbo.tblZahlungsbedingung TO janus_connect;
GRANT UPDATE (datBezahlDatum, fBezahlterBetrag, boolBezahlt)
ON dbo.tblRechnung
TO janus_connect;
```

**Hinweis:** Das Wiki wird parallel zu technischer Dokumentation und Benutzerhandbuch fortgeschrieben. Neue bestätigte Access-Abfragen, direkte SQL-Abfragen und Rechteänderungen werden hier mit kurzer Erklärung ergänzt. ORM-intern erzeugte Einzelabfragen werden nicht automatisch als Vollprotokoll aufgenommen, sofern sie keine eigenständige fachliche Bedeutung haben.

## 10. Webauswahl und Update für Lastschriften

**Zweck:** Die Webmaske übernimmt die fachliche Auswahl von `AAAmyLastschriften` und ergänzt den in der Oberfläche gewählten Fälligkeitszeitraum. Vor einem Sammelupdate wird dieselbe Auswahl erneut gelesen.

```sql
SELECT r.intID, r.intRechNr, r.intAufNr, r.strKundenNameAufRechnung,
       r.datFaelligkeitsDatum, r.boolBezahlt, r.fRechnungsbetrag,
       r.datSkonto1Bis, r.datSkonto2Bis, r.datSkonto3Bis,
       r.fRechnungsbetragMitSkonto1, r.fRechnungsbetragMitSkonto2,
       r.fRechnungsbetragMitSkonto3, a.intZahlungsbedingungID
FROM tblRechnung AS r
INNER JOIN tblAuftrag AS a ON a.intAufNr = r.intAufNr
WHERE r.boolBezahlt = 0
  AND a.intZahlungsbedingungID IN (3, 26)
  AND r.datFaelligkeitsDatum >= @FaelligAb
  AND r.datFaelligkeitsDatum < DATEADD(day, 1, @FaelligBis)
ORDER BY r.intID;
```

**Schreibvorgang pro Rechnung:** Der Zahlbetrag wird in der Anwendung nach der bestätigten Access-Reihenfolge ermittelt. Anschließend wird nur ein noch unbezahlter Datensatz aktualisiert. Alle Rechnungen des Sammellaufs werden innerhalb einer Transaktion verarbeitet.

```sql
UPDATE dbo.tblRechnung
SET datBezahlDatum = @Faelligkeitsdatum,
    fBezahlterBetrag = @ErmittelterZahlbetrag,
    boolBezahlt = 1
WHERE intID = @RechnungID
  AND boolBezahlt = 0;
```

**Sicherheitsprinzip:** Für `janus_connect` wurde kein pauschales UPDATE auf `tblRechnung` vergeben, sondern nur UPDATE auf `datBezahlDatum`, `fBezahlterBetrag` und `boolBezahlt`.
