# SQL-Statement-Wiki – tops.net Buchhaltung

Stand: 17.09.2026

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

## 9. Webauswahl und Update für Lastschriften

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

## 11. Branchenbericht (`Branchen`)

**Zweck:** Verknüpft Kunden mit ihren Branchen und liefert die Daten für den gruppierten Access-Bericht. Angezeigt werden Branche, Kundennummer, Kunde, Adresse und Telefon.

```sql
SELECT tblBranchen.strBezeichnung,
       tblKundenBranchen.intKundeID,
       tblKunde.strName,
       tblKunde.strStrasse,
       tblKunde.strOrt,
       tblKunde.strPLZ,
       tblKunde.strTelefon,
       tblBranchen.strCode
FROM tblKunde
INNER JOIN (tblBranchen
INNER JOIN tblKundenBranchen
    ON tblBranchen.strCode = tblKundenBranchen.strBranchenCode)
    ON tblKunde.intID = tblKundenBranchen.intKundeID;
```

## 12. Branchenexport

**Zweck:** Liefert die bestätigte Feldmenge des historischen Branchenexports. Die Webanwendung verwendet diese Daten für einen XLSX-Export.

```sql
SELECT tblBranchen.strBezeichnung,
       tblKunde.intID,
       tblKunde.strName,
       tblKunde.strTelefax,
       tblBranchen.strCode
FROM tblKunde
INNER JOIN (tblBranchen
INNER JOIN tblKundenBranchen
    ON tblBranchen.strCode = tblKundenBranchen.strBranchenCode)
    ON tblKunde.intID = tblKundenBranchen.intKundeID;
```

## 13. Accounting-Bericht ohne Zusatzinfos

**Zweck:** Liefert die abrechenbaren Accounting-Daten eines Kunden für Monat und Jahr. Der Kunde wird über Auftrag und Auftragsposition ermittelt; nur `boolAbrechenbar <> 0` wird berücksichtigt.

```sql
SELECT tblKunde.strName, tblAnbindungAuswertung.decMBin, tblAnbindungAuswertung.decMBout,
       tblAnbindungAuswertung.decGesamt, tblAnbindungAuswertung.intMonat,
       tblAnbindungAuswertung.intJahr, tblAnbindungAuswertung.strrechnungsinfo,
       tblKunde.strStrasse, tblKunde.strOrt, tblKunde.strPLZ, tblKunde.strAnrede,
       tblKunde.intID, tblAnbindungen.intID AS intAnbID, tblAnbindungen.boolAbrechenbar,
       tblAnbindungen.dateAbrechenbarStart, tblAnbindungen.dateAbrechenbarEnde,
       tblAnbindungen.intTyp, tblAnbindungen.intAnbindungReferenz,
       [Sekunden pro Anbindung].[Summe von intVerbindungsdauerInSec] AS sumsekunden
FROM tblKunde INNER JOIN (tblAuftrag INNER JOIN (tblAuftragPos INNER JOIN
     ((tblAnbindungen INNER JOIN tblAnbindungAuswertung
       ON tblAnbindungen.intID = tblAnbindungAuswertung.intAnbindungID)
      LEFT JOIN [Sekunden pro Anbindung]
       ON tblAnbindungen.intID = [Sekunden pro Anbindung].intSekundenID)
      ON tblAuftragPos.intID = tblAnbindungen.intAuftragsPos)
      ON tblAuftrag.intAufNr = tblAuftragPos.intAufNr)
      ON tblKunde.intID = tblAuftrag.intKID
WHERE tblAnbindungAuswertung.intMonat=[MONATzweistellig]
  AND tblAnbindungAuswertung.intJahr=[JAHRvierstellig]
  AND tblKunde.intID=[KundenNummer]
  AND tblAnbindungen.boolAbrechenbar<>0
ORDER BY tblAnbindungAuswertung.decGesamt DESC;
```

## 14. Accounting-Berichte mit Zusatzinfos

**Zweck:** Gemeinsame Datenbasis der Varianten **mit Zusatzsumme** und **ohne Zusatzsumme**. Im Unterschied zur vorherigen Abfrage wird nicht auf `boolAbrechenbar` gefiltert und der Kunde direkt über `tblAnbindungen.intKID` zugeordnet.

```sql
SELECT tblKunde.strName, tblAnbindungAuswertung.decMBin, tblAnbindungAuswertung.decMBout,
       tblAnbindungAuswertung.decGesamt, tblAnbindungAuswertung.intMonat,
       tblAnbindungAuswertung.intJahr, tblAnbindungAuswertung.strrechnungsinfo,
       tblKunde.strStrasse, tblKunde.strOrt, tblKunde.strPLZ, tblKunde.strAnrede,
       tblKunde.intID AS intKID, tblAnbindungen.intID, tblAnbindungen.boolAbrechenbar,
       tblAnbindungen.dateAbrechenbarStart, tblAnbindungen.dateAbrechenbarEnde,
       tblAnbindungen.intTyp, tblAnbindungen.intAnbindungReferenz AS intanbID,
       [Sekunden pro Anbindung].[Summe von intVerbindungsdauerInSec] AS sumsekunden
FROM ((tblAnbindungen INNER JOIN tblKunde ON tblAnbindungen.intKID = tblKunde.intID)
INNER JOIN tblAnbindungAuswertung ON tblAnbindungen.intID = tblAnbindungAuswertung.intAnbindungID)
LEFT JOIN [Sekunden pro Anbindung] ON tblAnbindungen.intID = [Sekunden pro Anbindung].intSekundenID
WHERE tblAnbindungAuswertung.intMonat=[MONATzweistellig]
  AND tblAnbindungAuswertung.intJahr=[JAHRvierstellig]
  AND tblKunde.intID=[KundenNummer]
ORDER BY tblAnbindungAuswertung.decGesamt DESC;
```

## 15. Sekunden pro Anbindung

**Zweck:** Summiert bei Dialin-Anbindungen vom Typ 3 die Verbindungsdauer des ausgewählten Kalendermonats je Anbindung.

```sql
SELECT tblAnbindungen.intID AS intSekundenID,
       Sum(tblAnbindungenDialinWerte.intVerbindungsdauerInSec) AS [Summe von intVerbindungsdauerInSec]
FROM tblAnbindungen INNER JOIN tblAnbindungenDialinWerte
  ON tblAnbindungen.intAnbindungReferenz = tblAnbindungenDialinWerte.IntDialinID
WHERE tblAnbindungenDialinWerte.datEnd >= DateValue("1" & "." & [MONATzweistellig] & "." & [JAHRvierstellig])
  AND tblAnbindungenDialinWerte.datEnd < DateAdd("m",1,DateValue("1" & "." & [MONATzweistellig] & "." & [JAHRvierstellig]))
  AND tblAnbindungen.intTyp=3
GROUP BY tblAnbindungen.intID;
```

## 16. Berechnete Felder der Accounting-Berichte

**Zweck:** Dokumentiert die im Access-Report selbst hinterlegte Geschäftslogik. Abweichende Start-/Enddaten werden als Hinweis ausgegeben. Bei Typ 3 wird `sumsekunden` als `H:MM:SS Stunden (N Sekunden)` formatiert. Die Variante **mit Zusatzsumme** summiert `decMBIn`, `decMBOut` und `decGesamt` über alle Datensätze. Die Variante **ohne Zusatzsumme** zeigt/summiert diese Werte nur, wenn `boolAbrechenbar <> 0`.


## 17. Produktpflege

Das Access-Formular `Alle Produkte mit Eigenschaften` verwendet direkt `tblProdukt`. Die Webanwendung liest und schreibt die bestätigten Produktfelder direkt in `accountings.dbo.tblProdukt`.

Referenzquellen der Kombinationsfelder:

```sql
SELECT intID, strBezeichnung FROM dbo.tblAbrechnungsArt;
SELECT intID, strBezeichnung FROM dbo.tblMengeSchluessel;
SELECT intID, strDatevKontierung, stDatevBezeichnung FROM dbo.tblDatevBezeichnungen ORDER BY strDatevKontierung;
SELECT intID, strBezeichnung FROM dbo.tblProduktGruppe;
SELECT intID, strBezeichnung FROM dbo.tblMwstSchluessel;
SELECT intID, strBezeichnung FROM dbo.tblStaffelgruppe ORDER BY strBezeichnung;
SELECT intID, strBezeichnung FROM dbo.tblLinearStaffel;
SELECT intID, strTarifname FROM dbo.tblZeittarife;
SELECT intID, strBezeichnung FROM dbo.tblBereichsStaffel ORDER BY strBezeichnung;
```

Domain-Accounting verwendet die separate Datenbank `domains`:

```sql
SELECT intID, strKonditionsName
FROM dbo.tblDomainKonditionen
ORDER BY strKonditionsName;
```

Neue Produkte erhalten eine neue `rowguid` mit `NEWID()`. Beim Wechsel auf Staffeltyp 5 wird entsprechend `Kombinationsfeld41_Change` `intMengenSchluessel = 1` gesetzt; bestehende Typ-5-Datensätze werden beim bloßen Öffnen nicht geändert.

## 18. Staffelgruppen und Staffelpreise

**Access-Hauptquelle:**

```sql
SELECT tblStaffelgruppe.*, tblStaffelgruppe.strBezeichnung
FROM tblStaffelgruppe
ORDER BY tblStaffelgruppe.strBezeichnung;
```

**Access-Unterformular:**

```sql
SELECT tblStaffelpreise.intID, tblStaffelpreise.intMenge,
       tblStaffelpreise.intvkpreis, tblStaffelpreise.intStaffelgruppeID
FROM tblStaffelpreise
ORDER BY tblStaffelpreise.intMenge;
```

Der Staffelrechner erzeugt die Preisstufen mit INSERTs in `tblStaffelPreise(intMenge,intvkPreis,intStaffelgruppeID)`. Im Web wird dieselbe Berechnungsreihenfolge transaktional ausgeführt; `rowguid` wird zusätzlich mit `NEWID()` gesetzt.


## 19. Linearstaffeln

**Zweck:** Stammdatenpflege für Staffeltyp 2 aus dem Access-Formular `frmLinearstaffel`. Die Datensatzquelle ist direkt `tblLinearStaffel`.

```sql
SELECT intID, intMengeFrei, floatPreisEinheit, strBezeichnung,
       floatBasisPreis, strAbrechnungseinheit
FROM dbo.tblLinearStaffel;
```

Für die Webpflege wurden ausschließlich die zum Access-Verhalten passenden Schreibrechte ergänzt:

```sql
GRANT INSERT, UPDATE ON dbo.tblLinearStaffel TO janus_connect;
```

DELETE bleibt nicht erlaubt.


## 20. Zeittarife und Zeitzonen

**Zweck:** Dokumentiert die bestätigte Datenbasis der Zeitabrechnung.

```sql
SELECT intID, strTarifname, intFreiSekunden, intMindestAbnahmeSekunden, intTaktSekunden
FROM dbo.tblZeittarife;

SELECT intID, intTarifID, datBeginn, datEnde, fMinutenpreis
FROM dbo.tblZeittarifeZonen;
```

Die Detailverknüpfung lautet `tblZeittarife.intID = tblZeittarifeZonen.intTarifID`. Reine Uhrzeiten liegen historisch als `datetime` mit Basisdatum `1899-12-30` vor.

## 21. Bereichsstaffeln

```sql
SELECT * FROM dbo.tblBereichsStaffel ORDER BY strBezeichnung;

SELECT intID, intStaffelGruppenID, fGrundgebuehr, fBereichsGrundgebuehr,
       fStueckpreis, intMengeAb, intMengeBis
FROM dbo.tblBereichsStaffelPreise;
```

Die Detailverknüpfung lautet `tblBereichsStaffel.intID = tblBereichsStaffelPreise.intStaffelGruppenID`.

## 22. Bandbreiten-Tarife

**Zweck:** Bestätigte Datenbasis des Access-Formulars `frmBandbreitenTarife`.

```sql
SELECT * FROM dbo.tblBandbreiteStaffel;

SELECT intID, intStaffelGruppenID, intMenge, fVkPreis
FROM dbo.tblBandbreiteStaffelPreise
ORDER BY intMenge;
```

Die Detailverknüpfung lautet `tblBandbreiteStaffel.intID = tblBandbreiteStaffelPreise.intStaffelGruppenID`.

## 23. Domainkonditionen

**Zweck:** Datenbasis der Abrechnungsart #5 in der separaten Datenbank `domains`.

```sql
SELECT intID, strKonditionsName, intR_AbrechnungEinheit, intR_AbrechnungIntervall,
       fR_IntervallPreis, intEnthaltenAnzahl, intEnthaltenEinheit,
       fEinrichtungsPreis, strEinrichtungRechnungsInfo,
       boolFuerKonnektierung, boolFuerSecondary, boolVeraltet
FROM dbo.tblDomainKonditionen;
```

Access speichert am Formular den Filter `strKonditionsName Like "*prime*"`; da er im bestätigten Formularzustand offensichtlich nicht aktiv ist, wird er in der Webpflege nicht automatisch erzwungen. Die beiden Einheitenlisten sind absichtlich unterschiedlich belegt.

## 24. SMS-Zugänge

**Zweck:** Datenbasis der SMS-Zugangspflege. Kennwortfelder werden absichtlich nicht in lesenden Webabfragen zurückgegeben.

```sql
SELECT intSMSZugaengeID, strSMSAccountNummer, intKundenNr, datErstelltAm,
       strErstelltVon, strBemerkung, strRechnungsinfo,
       strGWCorporateName, strGWCorporateDepartmentName, strGWRegistrationName,
       boolGWAllowNewAccounts, strGWAdditionalInformation, boolGWUseIPRestriction,
       boolIsCustomerAccount, strGWSingleAccountUserName, strGWSingleAccountEmail,
       strGWSingleAccountOriginator, strGWSingleAccountTYPE
FROM dbo.tblSMSZugaenge;
```

Die Spalten `strKennwort` und `strGWRegistrationPassword` werden nur gezielt bei einer expliziten Kennwortänderung geschrieben und nicht an das Formular zurückgegeben.

## 25. Anbindungen / Verbindungen

**Zweck:** Zentrale Zuordnung einer Auftragsposition zu technischen Accounting-Referenzen.

```sql
SELECT an.*, ap.intAufNr, a.intKID
FROM dbo.tblAnbindungen AS an
JOIN dbo.tblAuftragPos AS ap ON ap.intID = an.intAuftragsPos
JOIN dbo.tblAuftrag AS a ON a.intAufNr = ap.intAufNr
ORDER BY an.intID;

SELECT intID, intAnbindungID, decMBin, decMBout, intMonat, intJahr, decGesamt, strrechnungsinfo, intVerbindungsdauerInSec
FROM dbo.tblAnbindungAuswertung
WHERE intAnbindungID = ?;
```

Die Referenzbeziehungen lauten für Typ 1/2/3/5/6/7 jeweils `tblAnbindungen.intAnbindungReferenz` auf die technische ID. Typ 4 verwendet stattdessen `tblAnbindungen.intID = tblAnbindungAuswertung.intAnbindungID`.

Im Modern-Frontend werden die bereits migrierten technischen Referenzen zusätzlich direkt verlinkt: Typ 1 öffnet Netze, Typ 2 Ports und Typ 3/5 Dialins. Die Classic-Ansicht erhält diese Zusatznavigation bewusst nicht. Die moderne Ergebnisliste markiert ihre Detaillinks zusätzlich explizit als moderne Ansicht, damit ein noch geöffnetes Classic-Fenster derselben Anbindung nicht wiederverwendet wird. Dadurch ändert sich keine SQL-Beziehung.

Die Access-artigen Feldfilter der Classic-Maske werden ausschließlich über feste Feldkennungen umgesetzt: `intID`, `intAnbindungReferenz`, `strKopieRechnungsinfo`, Kunden-ID/-name, Auftrags-ID, Auftragspositions-ID sowie bestätigte technische Anzeigefelder aus Netz, Port, Dialin, Domains, SMS und Fremdaccounting. Filterwerte werden als Parameter gebunden; frei übergebene Tabellen- oder Spaltennamen werden nicht in SQL übernommen. Technische Feldfilter verwenden typabhängige korrelierte `EXISTS`-Abfragen auf die jeweilige Referenztabelle.

Die ID der Auftragsposition ist im Classic-Formular kein graues Filterfeld. Sie wird wie in Access separat angezeigt; darüber steht der Hinweis, dass die Anbindung über diese Auftragspositions-ID verknüpft sein muss, damit das Accounting funktioniert.

## 26. Netze

**Zweck:** Pflege der technischen IP-Netze aus dem Access-Formular `frmNetze`.

```sql
SELECT intID, strNetzwerk, intNetzmaske, intGatewayRouter,
       intKundenNetz, intAccountingEingerichtet, intInUse,
       strVerwendung, strBemerkung, strStandort, strrechnungsinfo, rowguid
FROM dbo.tblAnbindungNetze
ORDER BY intID DESC;
```

Bei Neuanlagen erzeugt SQL Server `intID` als Identity; `rowguid` wird von der Webanwendung mit `NEWID()` gesetzt. Die Checkboxwerte werden als `-1` bzw. `0` gespeichert. DELETE wird nicht verwendet.

## 27. Ports

**Zweck:** Pflege der Port-Accounting-Stammdaten.

```sql
SELECT intid, strRouterIP, strMIBVarIN, strMIBVarOUT, strPortDescription,
       strrechnungsinfo, decOverrunLimit, boolDeaktiviert,
       strMIBVarDESCR, strIfDescrMust, strIfDescrCurrent, dateIfDescrCurrent
FROM dbo.tblPort
ORDER BY intid DESC;
```

Die Webpflege liest `strSNMPCommunity` nur für das eigentliche Bearbeitungsformular bzw. die Eingabehilfe beim Anlegen. Community-Werte werden bewusst nicht in Repository oder Dokumentation festgehalten. `strIfDescrCurrent` und `dateIfDescrCurrent` sind reine Anzeigeinformationen und werden durch die Webpflege nicht überschrieben.

## 28. Dialins und Einwahlnummern

**Zweck:** Pflege der Radius-Zugänge ohne Rücklesen vorhandener Kennwörter.

```sql
SELECT intID, strLogin, strrechnungsinfo, strIP, bInaktiviertesDialin,
       dateDialinDisabled, intMaxKanaele, intMaxMehrfachLogins, boolCallback,
       intSessionTimeout, intMaxIdle, bKundeIstInternetProfAbonnent, rowguid
FROM dbo.tblAnbindungDialin
ORDER BY intID DESC;

SELECT intID, intDID, intEinwahlnummerID, datBeginn, datEnde
FROM dbo.tblAnbindungDialinEinwahlnummern
WHERE intDID = ?
ORDER BY datBeginn;
```

`strKennwort` fehlt absichtlich in der SELECT-Liste. Bei Updates wird das Feld nur gesetzt, wenn der Benutzer ausdrücklich ein neues Kennwort eingibt. Die Unterformular-Beziehung lautet `tblAnbindungDialin.intID = tblAnbindungDialinEinwahlnummern.intDID`.

## 29. Domain-Einträge / DNS-Zonen

**Zweck:** Gemeinsame Domainansicht und zugehörige DNS-Zoneneinträge.

```sql
SELECT ad.strTyp,
       CASE WHEN RTRIM(ad.strTyp) = 'DOMAIN' THEN d.strDomainname ELSE da.strDomainName END AS Domainname,
       ad.intID, d.strDomainKlartextname
FROM dbo.tblAllgemeineDomain AS ad
LEFT JOIN dbo.tblDomains AS d ON ad.intDomainID = d.intID
LEFT JOIN dbo.tblDomainAuftrag AS da ON ad.intDomainID = da.intID
ORDER BY CASE WHEN RTRIM(ad.strTyp) = 'DOMAIN' THEN d.strDomainname ELSE da.strDomainName END;

SELECT intID, intIDAllgemeineDomain, strName, strTyp, intTTL, strAdresse, Datum
FROM dbo.tblDomainEintraege
WHERE intIDAllgemeineDomain = ?
ORDER BY intID;
```

Die Beziehung lautet `tblAllgemeineDomain.intID = tblDomainEintraege.intIDAllgemeineDomain`. Ganze Domains werden in diesem Modul nicht gelöscht.

## 30. Domain eintragen

**Zweck:** Neue Domain einschließlich allgemeiner Domain-ID und Standard-Zoneneinträgen anlegen.

Die Webanwendung verwendet eine Transaktion. Die neue `tblDomains.intID` und danach `tblAllgemeineDomain.intID` werden jeweils mit `OUTPUT INSERTED.intID` ermittelt. Neue Zonen erhalten automatisch SOA sowie zwei NS-Einträge mit `intTTL = 3600`. `intDNSSEC` wird für diesen Access-Migrationsworkflow explizit auf `0` gesetzt. `datRegistriertAm` und `tblDomainEintraege.Datum` werden direkt im SQL Server mit `GETDATE()` gesetzt; damit wird die Datumsinterpretation nicht von Session-Sprache oder `DATEFORMAT` abhängig.

Die Kundennummer wird vor dem Insert gegen `topsnetdb_safe.dbo.tblKunde.intID` geprüft. Der historische Outlook-Maschinenbefehl zur DNS-Aktualisierung ist nicht Bestandteil der Webimplementierung.


### Nicht migrierte Alt-Funktionen im Domain-Menü

Die Access-Menüpunkte **Handles pflegen**, **Owner pflegen**, **Look up starten** und **Aktuelle Domain-Aufträge** bleiben im Web-Frontend deaktiviert. Diese Funktionen wurden im bisherigen Arbeitsablauf nicht genutzt und werden deshalb nicht separat migriert. Falls solche Funktionen später wieder benötigt werden, sollen sie im Rahmen der geplanten zentralen Domainverwaltung über die DENIC-API neu umgesetzt werden, statt die ungenutzte Access-Logik nachzubauen. Das gilt ebenfalls für **Aktuelle Domain-Aufträge**.

## 31. IPv4 Reverse

**Zweck:** Lesende Übersicht der bestehenden Reverse-DNS-Kundenzuordnungen.

```sql
SELECT intID, intIPbyte1, intIPbyte2, intIPbyte3, intIPbyte4, intKundenID
FROM dbo.tblDNSipv4ReverseEditor
ORDER BY intIPbyte1, intIPbyte2, intIPbyte3, intIPbyte4;
```

Die Webansicht schreibt bewusst nicht in diese Tabelle. Der historische Access-Formfilter `intIPbyte3 = 185` wird nicht als globale Einschränkung übernommen.

## 32. Domains zum Kunden zuordnen

**Zweck:** Noch nicht zugeordnete Domains einem Kunden zuweisen.

```sql
SELECT intID, strDomainname, datRegistriertAm
FROM dbo.tblDomains
WHERE UMSTELLUNGintKundenID IS NULL
ORDER BY strDomainname;

UPDATE dbo.tblDomains
SET datRegistriertAm = DATEFROMPARTS(?, ?, ?),
    UMSTELLUNGintKundenID = ?
WHERE intID = ?
  AND UMSTELLUNGintKundenID IS NULL;
```

Die Kundenauswahl kommt aus `topsnetdb_safe.dbo.tblKunde`. Die jüngste Auftragsposition wird aus `accountings.dbo.tblAuftrag` und `tblAuftragPos` über `MAX(datErstelltAm)` je `intKID` ermittelt.


## 33. Domains zu einer Auftragsposition zuordnen

**Zweck:** Domain-Accounting-Verknüpfung zwischen einer Domain und einer vorhandenen Domain-Konditionsposition anlegen.

Nicht zugeordnete Domains werden fachlich dadurch bestimmt, dass für die Domain noch keine Anbindung des Typs 6 existiert. Passende Zielpositionen müssen zum selben Kunden gehören und `tblAuftragPos.intStaffelTyp = 5` besitzen.

Beim Speichern wird sinngemäß folgender Datensatz angelegt:

```sql
INSERT INTO dbo.tblAnbindungen
    (intKID, intTyp, intAnbindungReferenz, boolAbrechenbar,
     dateAbrechenbarStart, dateAbrechenbarEnde, strKopieRechnungsinfo,
     intAuftragsPos, rowguid)
VALUES
    (NULL, 6, @DomainID, 1, @RegistriertAm, '2029-12-31', @Domainname,
     @AuftragsPosID, NEWID());
```

Der bestätigte Bedienablauf filtert zunächst nach Kundennummer, wählt dann Domain und passende Domain-Auftragsposition und legt die Anbindung nach genau einer Bestätigung an. Die Weboberfläche bildet diesen Ablauf ohne das historische Access-Häkchen **Alle** ab.

### Konditionsrabatte zu Domain-Auftragspositionen

**Zweck:** Rabatt auf Einrichtungsgebühr und regulären Preis einer Domain-Konditionsposition lesen bzw. speichern.

```sql
SELECT intID, intAuftragsPosID, fRabattEinrichtung, fRabattRegulaer
FROM domains.dbo.tblDomainKonditionenRabatte
WHERE intAuftragsPosID = @AuftragsPosID;
```

Existiert kein Datensatz, wird einer angelegt; existiert bereits einer, werden `fRabattEinrichtung` und `fRabattRegulaer` aktualisiert. Beide Werte werden in der Webanwendung auf 0 bis 100 % begrenzt.

## Rechnungstool: gemeinsame SQL-Dokumentation

Alle im Zuge der Rechnungstool-Migration bestätigten SELECT-, INSERT-, UPDATE- und Berechtigungs-Statements werden in diesem bestehenden Wiki ergänzt. Für das Rechnungstool wird bewusst kein separates SQL-Wiki angelegt. Neue Statements werden erst nach fachlicher Prüfung und tatsächlicher Implementierung dokumentiert; geplante oder nur aus dem Altcode vermutete Schreibzugriffe gelten nicht als freigegeben.

Die erste Festpreis-/Intervallberechnung unter `/fakturierung` liest zusätzlich `accountings.dbo.BETAtblAbrechnungsArt`. Benötigt werden ausschließlich `intID`, `intEinheiten` und `strDimension`, um die im Alttool verwendeten Intervalle `TAG`, `MONAT`, `JAHR` und `EINMALIG` aufzulösen. Dafür ist ein zusätzliches reines SELECT-Recht erforderlich:

```sql
USE accountings;
GRANT SELECT ON dbo.BETAtblAbrechnungsArt TO janus_connect;
```

### Phase 1: Staffeltyp 1 und Linearstaffeltyp 2

Bei aktivierter Option **Accountings berücksichtigen** nutzt die lesende Vorschau für Staffeltyp 1 die bereits freigegebenen Tabellen `tblAnbindungen`, `tblAnbindungAuswertung`, `tblStaffelgruppe` und `tblStaffelpreise`. Fachlich wird wie im Alttool die Monatsnutzung über `SUM(tblAnbindungAuswertung.decGesamt)` gebildet und die kleinste Preisstufe gewählt, deren `intMenge` die Nutzung abdeckt.

Für Staffeltyp 2 werden `tblAnbindungen`, `tblAnbindungAuswertung` und `tblLinearStaffel` gelesen. Aus `intMengeFrei`, `floatPreisEinheit`, `floatBasisPreis` und `strAbrechnungseinheit` wird zusammen mit dem Monats-Accounting der Nettobasisbetrag bestimmt. Für diesen Schritt sind keine zusätzlichen Schreibrechte erforderlich; die bereits vorhandenen SELECT-Rechte reichen aus.

Für Staffeltyp 3 liest der Testlauf `tblAnbindungen`, `tblAnbindungDialin`, `tblAnbindungenDialinWerte`, `tblZeittarife` und `tblZeittarifeZonen`. Vorhandene `fNettoPreis`-Werte aus dem EVN werden wie im Alttool bevorzugt; fehlende Preise werden ausschließlich im Arbeitsspeicher aus Freisekunden, Mindestabnahme, Taktung und Minutenpreiszonen berechnet. Anders als die historische COM-Komponente führt der Web-Testlauf dabei **kein UPDATE** auf `tblAnbindungenDialinWerte.fNettoPreis` aus.

Für Staffeltyp 4 und 7 liest die Vorschau `tblAnbindungAuswertung.decMBIn/decMBOut` sowie `tblBandbreiteStaffelPreise`. Typ 4 verwendet `MAX(In,Out)`, Typ 7 `SUM(In+Out)`, rechnet die Monatsmenge in durchschnittliche kBit/s um und wählt die kleinste Preisstufe mit `intMenge >=` dem ermittelten Wert. Die dafür nötigen SELECT-Rechte bestanden bereits; neue Schreibrechte sind nicht erforderlich.

Für Staffeltyp 5 liest die Vorschau zusätzlich `domains.dbo.tblDomainKonditionen`, `domains.dbo.tblDomainKonditionenRabatte` und `domains.dbo.tblDomains` sowie die Domain-Anbindungen (`tblAnbindungen.intTyp = 6`) aus `accountings`. Es werden ausschließlich bestehende SELECT-Rechte genutzt. Einrichtungs-/Anfangsphase und reguläre Monats- bzw. Jahresintervalle werden im Arbeitsspeicher bestimmt; an Domain- oder Accountingtabellen erfolgen keine Schreibzugriffe.

Für Staffeltyp 6 werden `tblBereichsStaffel`, `tblBereichsStaffelPreise`, `tblAnbindungen` und `tblAnbindungAuswertung` gelesen. Der passende Bereich muss `intMengeAb <= SUM(decGesamt) <= intMengeBis` erfüllen; der Nettobetrag ergibt sich aus Grundgebühr, Bereichs-Grundgebühr und Stückpreis für die Menge oberhalb `intMengeAb`.

Für die Vorberechnung von Staffeltyp 1 liest der Testlauf zusätzlich `accountings.dbo.tblAccountingKonto` (`intMB`, `intAufPosID`, `intRechnungsMonat`, `intRechnungsJahr`). Das historische VB.NET-Tool löscht und schreibt dort beim Lauf den Folgemonat neu. Die Webvorschau führt diese Änderung **nicht** aus, sondern simuliert den neuen Stand nur. Benötigt wird daher ausschließlich:

```sql
USE accountings;
GRANT SELECT ON dbo.tblAccountingKonto TO janus_connect;
```

Vorhandene Berechnungen aus `accountings.dbo.tblAuftragPosBerechnet` werden für den Paritätscheck weiterhin ausschließlich gelesen. Der Web-Testlauf schreibt weder dort noch in `tblRechnung` oder `tblAuftragPos`. Die Zugriffskontrolle erfolgt unabhängig davon über Kerberos/SPNEGO, die AD-Gruppen `DB-Webapp-Users` und `DB-Webapp-Rechnungstool`, den lokalen Authz-Helper sowie Laravel-Middleware.

### Kompletter Auftragstestlauf

#### Verbindliche Kundendatenquellen

Die Prüfung des Alttool-Codes am 18.09.2026 bestätigt folgende Aufteilung:

- `accountings.dbo.tblAuftrag`: Auftragsnummer, Kundennummer, Zahlungsbedingung und ID der Rechnungsanschrift.
- `topsnetdb_safe.dbo.tblKunde`: Kundenstammdatensatz und DATEV-Kundenkonto.
- `accountings.dbo.tblRechnungsanschrift`: tatsächlicher Rechnungsempfänger, Postanschrift, E-Mail, Umsatzsteuer-ID und Bankdaten.

Die Auftragsauswahl des Alttools verlangt gleichzeitig `tblAuftrag.intAnschriftID = tblRechnungsanschrift.intID` und `tblAuftrag.intKID = tblRechnungsanschrift.intKID`. Die Webabfrage bildet beide Bedingungen nach. Auftrag 5772/Kunde 6384 ist im aktuellen Stand konsistent. Eine Gesamtkontrolle aller 227 vom Rechnungstool referenzierten Kundennummern ergab keinen fehlenden Datensatz in `topsnetdb_safe`. Die historischen Aufträge 5548 und 5578 haben dagegen eine fremde Rechnungsanschrift und werden entsprechend dem Alttool nicht als Fakturierungskandidaten ausgewählt.

Der read-only Auftragstestlauf liest für die Konsistenz- und Rechnungsansicht zusätzlich `accountings.dbo.tblAuftrag`, `accountings.dbo.tblRechnungsanschrift`, `accountings.dbo.tblZahlungsbedingung`, `accountings.dbo.tblDatevBezeichnungen` sowie `topsnetdb_safe.dbo.tblKunde`. `tblZahlungsbedingung` stammt wie im VB.NET-Alttool ausdrücklich aus `accountings`. Aus diesen Tabellen werden nur Empfänger-, Zahlungs-, Versand- und DATEV-Informationen gelesen. IBAN wird in der Webausgabe maskiert. Die zusammengefasste Testrechnung selbst existiert ausschließlich im Arbeitsspeicher der Webanwendung; es gibt dafür kein `INSERT` oder `UPDATE`.

### Phase 1: Kunden- und Gesamttestlauf

Der Kunden- und Gesamttestlauf verwendet keine zusätzlichen Schreibrechte. Die Kandidatenmenge basiert auf derselben `tblAuftrag`/`tblRechnungsanschrift`-Abfrage wie die Auftragsliste. Für einen Kundenlauf wird zusätzlich `a.intKID = @Kundennummer` gesetzt; der Gesamtlauf verwendet keinen Auftrag-/Kunden-/Suchfilter und verarbeitet alle Kandidaten des gewählten Zeitraums und Abrechnungstyps. Die Einzelaufträge lesen anschließend dieselben Tabellen wie die Einzelvorschau (`tblAuftragPos`, `tblAuftragPosBerechnet`, `tblDatevBezeichnungen`, `tblZahlungsbedingung`, `tblRechnungsanschrift` sowie je nach Staffeltyp die jeweiligen Accounting-/Domain-Tabellen). Ein Fehler eines Auftrags wird in der Webanwendung isoliert und beendet die übrigen SELECT-Prüfungen nicht. Die Box **Tägliche Konsistenzprüfung** führt beim Anzeigen selbst keinen Gesamttest aus. Die ursprünglichen Auftragsprüfungen benötigen keine zusätzlichen SQL-Rechte; die später ergänzte Accounting-Zustandsprüfung benötigt die unten dokumentierten vier SELECT-Rechte. Der Befehl `php artisan invoice:check-consistency` prüft täglich um 08:00 Uhr `Europe/Berlin` alle drei Abrechnungsarten für den letzten abgeschlossenen Monat sowie grundlegende Auftrags-/Rechnungsanschrift-Zuordnungen. Der Laravel-Scheduler läuft dafür im Compose-Service `scheduler`. Das Ergebnis wird unter dem Cache-Schlüssel `invoice_consistency_report` dauerhaft gespeichert und mit Prüfzeitpunkt sowie Prüfzeitraum im Web-Frontend angezeigt. Der Prüflauf ist vollständig lesend.

Für den Kunden-/Gesamttestlauf selbst sind **keine zusätzlichen GRANTs** erforderlich. Die später ergänzte Accounting-Zustandsprüfung ist im folgenden Abschnitt getrennt dokumentiert. Es werden weiterhin keine `INSERT`, `UPDATE` oder `DELETE` für `tblRechnung`, `tblAuftragPosBerechnet` oder `tblAccountingKonto` ausgeführt.

### Stored-Procedure-Inventar der drei Altdatenbanken

Die vollständige Bestandsaufnahme vom 18.09.2026 enthält 211 Procedures: 109 in `accountings`, 40 in `domains` und 62 in `topsnetdb_safe`. Davon sind 97 fachliche/eigene Procedures. Die übrigen 114 Einträge sind alte `dt_*`-Visual-SourceSafe- und SQL-Diagramm-Procedures. Alle Definitionen waren lesbar; keine Procedure ist verschlüsselt. Keine Procedure vergibt oder reserviert Rechnungsnummern und keine schreibt in `tblRechnung`. Nur `GetMaxMahnstufeFuerKID` und `GetOffeneRechnungenForKID` lesen `intRechNr`.

Für den Rechnungslauf sind insbesondere `spCheckAufAbrechnungsStart`, `spCheckAufAktuelleAccountings`, `CalcAnbindungAuswertungByJahrAndMonat`, `CalcAnbindungAuswertungByJahrAndMonatONEanbindung`, `UpdateAnbindungenRechnungsinfo`, `UpdateJahresUmsatzProKunde`, `GetMaxMahnstufeFuerKID` und `GetOffeneRechnungenForKID` relevant. Die Webapp ruft die beiden alten Prüf-Procedures nicht auf, da diese E-Mails versenden und temporäre Tabellen anlegen. Sie bildet deren aktive Kontrollen stattdessen mit reinen `SELECT`-Abfragen nach.

### Zusätzliche Leserechte für Accounting-Zustandsprüfung

Die tägliche Prüfung benötigt zusätzlich folgende eng begrenzten Rechte:

```sql
USE [accountings];
GRANT SELECT ON OBJECT::dbo.tblAccountingIntervall TO [janus_connect];
GRANT SELECT ON OBJECT::dbo.tblAccountingNetzeTageswerte TO [janus_connect];
GRANT SELECT ON OBJECT::dbo.tblAnbindungAuswertung TO [janus_connect];
GRANT SELECT ON OBJECT::dbo.tblPort TO [janus_connect];
```

Die vier GRANTs wurden am 18.09.2026 auf dem SQL-Server-2019-System ausgeführt und anschließend mit dem Benutzer `janus_connect` erfolgreich geprüft. Fehlt später eines dieser Rechte, bricht der Tageslauf nicht ab. Die Oberfläche zeigt stattdessen einen Systempunkt der Kategorie **SQL-Berechtigung**.

### Erweiterung des täglichen 08:00-Uhr-Plans

Der bestehende Scheduler-Aufruf `invoice:check-consistency` bleibt täglich um 08:00 Uhr in `Europe/Berlin` bestehen. Vor dem Auftrags-/Gesamttest ergänzt `InvoiceAccountingHealthCheckService` folgende rein lesende Systemprüfungen:

| Prüfung | Datenquelle | Grenzwert |
|---|---|---|
| Switch-Accounting | `tblAccountingIntervall.MAX(dateEnddatum)` | nicht älter als 60 Minuten |
| HERMES-IP-Accounting | `tblAccountingNetzeTageswerte.MAX(dateofRecordCreation)`, Quelle `hermes` | nicht älter als 60 Minuten |
| Accounting-Monatssummen | `tblAnbindungAuswertung.MAX(dateofRecordCreation)` | nicht älter als 26 Stunden |
| Portbeschreibung Soll/Ist | aktive Zeilen aus `tblPort` | keine Abweichung zwischen `strIfDescrMust` und `strIfDescrCurrent` |
| Port-Aktualisierung | `tblPort.MIN(dateIfDescrCurrent)` für aktive Ports | nicht älter als 14 Stunden |

Die Grenzwerte entsprechen den aktiven Teilen der Alt-Procedures. Im Altcode auskommentierte Kontrollen für Dial-in, SMS, STHS3 und die deaktivierte BONN9-Benachrichtigung werden nicht reaktiviert. Die Webprüfung verschickt keine E-Mails, legt keine globale temporäre Tabelle an und führt die Alt-Procedures nicht aus. Jeder einzelne SQL-Fehler wird isoliert als Systempunkt gespeichert; die übrigen Prüfungen laufen weiter. Systempunkte erscheinen vor den auftragsbezogenen Punkten und besitzen keinen Auftragslink.

Der Kontrolllauf nach der Rechtevergabe am 18.09.2026 konnte alle Quellen lesen. Zu diesem Zeitpunkt lagen der letzte Switchwert bei 17.09.2026 21:21 Uhr, der letzte HERMES-Wert bei 17.09.2026 21:20 Uhr und die letzte Monatssumme bei 17.09.2026 02:00 Uhr. Außerdem bestanden acht Soll-/Ist-Abweichungen bei aktiven Ports. Diese Werte sind ein zeitbezogener Prüfstand und keine statische Sollvorgabe.

### Rechnungsnummern-Simulation

`tblRechnung.intRechNr` verwendet das Format `JJJJnnnnnn`. Die Vergabelogik liegt nicht in einer Stored Procedure, sondern in `komponenteFakturierungswesen.dll`: Das Alttool liest und aktualisiert `dbo.tblRechnungsNummern` über `intRechnungsJahr` und `intLfdNr`; die DLL erzwingt dabei eine laufende Datenbanktransaktion. Die Webapp liest diesen Zähler für die rein lesende Simulation, prüft parallel höchste gespeicherte Nummer, Duplikate und abweichende Jahrespräfixe und meldet eine Abweichung zwischen Zähler und Bestand. Sie reserviert keine Nummer und schreibt nicht. Fehlt das SELECT-Recht, nutzt sie vorübergehend sichtbar gekennzeichnet `MAX(intRechNr)+1` als Fallback.

Erforderliches zusätzliches Leserecht in `accountings`:

```sql
USE [accountings];
GRANT SELECT ON OBJECT::dbo.tblRechnungsNummern TO [janus_connect];
```

Die produktive Schreiblogik ist inzwischen technisch vorbereitet, bleibt aber über `INVOICE_WRITES_ENABLED=false` serverseitig deaktiviert. Bei einer späteren Freigabe sperrt die Webapp Auftrag und Jahreszähler mit `UPDLOCK, HOLDLOCK`, wiederholt Berechnung und Fakturierbarkeitsprüfung innerhalb derselben SQL-Server-Transaktion und schreibt anschließend Zähler, `tblRechnung`, `tblAuftragPosBerechnet` sowie bei Staffel-Vorausberechnung `tblAccountingKonto`. Jede Abweichung oder jeder SQL-Fehler führt zum vollständigen Rollback. Vor dem Commit wird zusätzlich ein exakter Bestätigungstext verlangt; erfolgreiche Schreibvorgänge werden mit AD-Benutzer, Auftrag, Rechnungs-ID und Rechnungsnummer protokolliert.

Die dafür auf dem Testserver benötigten Rechte sind bewusst objektgenau:

```sql
USE [accountings];
GRANT SELECT, UPDATE, INSERT ON OBJECT::dbo.tblRechnungsNummern TO [janus_connect];
GRANT SELECT, INSERT ON OBJECT::dbo.tblRechnung TO [janus_connect];
GRANT SELECT, INSERT ON OBJECT::dbo.tblAuftragPosBerechnet TO [janus_connect];
GRANT SELECT, INSERT, DELETE ON OBJECT::dbo.tblAccountingKonto TO [janus_connect];
```

Ein unabhängiges `MAX()+1` wird beim Schreiben niemals verwendet. Weichen Zählertabelle und höchste vorhandene Jahresnummer voneinander ab, bricht die Transaktion ab. Die Freigabe erfolgt erst nach einem kontrollierten Testfall durch `INVOICE_WRITES_ENABLED=true`; bis dahin zeigt die Oberfläche nur den Bereitschaftsstatus.

### Historischer Paritätsvergleich

Der Paritätsvergleich im Rechnungstool nimmt eine Rechnungsnummer oder interne Rechnungs-ID entgegen. Er liest `tblRechnung` sowie ausschließlich die über `tblAuftragPosBerechnet.intRechnungIntID` tatsächlich zugeordneten historischen Berechnungszeilen. Die gespeicherten `BerechnetZum`-Termine bilden den exakten Wiederholungszeitraum; aktuelle Einfrierung und der Status „bereits berechnet“ werden nur für diese Simulation ignoriert. Anschließend werden Netto, Steuer, Brutto, Positionsbeträge und Positionsrabatte der Altrechnung mit der heutigen Web-Berechnung verglichen. Differenzen ab einem Cent, fehlende Web-Zeilen und zusätzliche Web-Zeilen werden sichtbar markiert. Änderungen an aktuellen Stammdaten oder Accountingwerten können damit bewusst als Abweichung erscheinen. Der Vergleich schreibt keinerlei Daten.

### Phase 1: Aufträge für die Vorschau

**Zweck:** Kandidaten des bisherigen Rechnungstool-Auftragsfensters lesen. Die Webanwendung setzt die Auswahl mit Query Builder um; fachlich entsprechen die Filter den folgenden Bedingungen. `@Von` und `@Bis` werden in der Anwendung mit `DATEFROMPARTS` parametrisiert, um localeabhängige SQL-Server-Datumsumwandlungen zu vermeiden.

```sql
SELECT a.intAufNr, a.intKID, a.datFakturierAb, a.datStorniereAb,
       a.strBeschreibung, a.boolEmailRechnung, a.strAbrechnungshinweis,
       a.boolVoraus, a.boolDomainrechnung, a.boolEingefroren,
       ra.strEmail
FROM accountings.dbo.tblAuftrag AS a
INNER JOIN accountings.dbo.tblRechnungsanschrift AS ra
    ON ra.intID = a.intAnschriftID
   AND ra.intKID = a.intKID
WHERE a.boolRechnungstool = 1
  AND ISNULL(a.boolSponsoring, 0) = 0
  AND a.datFakturierAb < DATEADD(day, 1, @Bis)
  AND (a.datStorniereAb IS NULL OR a.datStorniereAb > @Von);
```

Zusätzlich gelten je Auswahlart: **Nachträglich** `boolVoraus = 0` und `boolDomainrechnung = 0`; **Im Voraus** `boolVoraus = 1`; **Domainaufträge** `boolVoraus = 0` und `boolDomainrechnung = 1`. Das letzte Rechnungsdatum wird read-only als `MAX(tblRechnung.datRechnungsDatum)` je Auftragsnummer ergänzt.

### Phase 1: Positions- und Berechnungsstand

```sql
SELECT intID, strBeschreibung, intMenge, fEndpreis, fRabattInProzent,
       intMwstsatz, intAbrechnungsArt, intStaffelTyp, intStaffelgruppe,
       datFakturierAb, datFakturierBis, datVorberechnenBis, boolIstAnbindung
FROM accountings.dbo.tblAuftragPos
WHERE intAufNr = @Auftragsnummer
ORDER BY intID;

SELECT intAufPosID, COUNT(*) AS anzahl, MAX(BerechnetZum) AS zuletztBerechnet
FROM accountings.dbo.tblAuftragPosBerechnet
WHERE intAufPosID IN (@PositionsIDs)
GROUP BY intAufPosID;
```

Diese Statements zeigen nur gespeicherte Rohdaten und den historischen Berechnungsstand. Sie berechnen noch keine neuen Rechnungspositionen und führen keine INSERT-, UPDATE- oder DELETE-Operation aus.

## 34. Berechtigungs-Statements für `janus_connect`

**Zweck:** Dokumentiert die im Migrationsprojekt bewusst vergebenen Minimalrechte. Die Statements enthalten keine Zugangsdaten.

Für `accountings`:

```sql
USE accountings;
GO

GRANT SELECT, INSERT, UPDATE ON dbo.tblAnbindungen TO janus_connect;
GRANT SELECT, INSERT, UPDATE ON dbo.tblPort TO janus_connect;
GRANT SELECT, INSERT, UPDATE ON dbo.tblAnbindungDialin TO janus_connect;
GRANT SELECT, INSERT, UPDATE ON dbo.tblAnbindungDialinEinwahlnummern TO janus_connect;
GRANT SELECT, INSERT, UPDATE ON dbo.tblAnbindungNetze TO janus_connect;
GRANT SELECT ON dbo.tblAnbindungAuswertung TO janus_connect;
GRANT SELECT ON dbo.tblDomains TO janus_connect;
GRANT SELECT ON dbo.tblDNSipv4ReverseEditor TO janus_connect;
GRANT SELECT ON dbo.tblAuftragPosBerechnet TO janus_connect;
GRANT SELECT ON dbo.tblDatevBezeichnungen TO janus_connect;
GRANT SELECT ON dbo.tblZahlungsbedingung TO janus_connect;
GRANT SELECT ON dbo.tblAnbindungenDialinWerte TO janus_connect;
GRANT SELECT ON dbo.tblAbrechnungsArt TO janus_connect;
GRANT SELECT ON dbo.tblMengeSchluessel TO janus_connect;
GRANT SELECT ON dbo.tblProduktGruppe TO janus_connect;
GRANT SELECT, INSERT, UPDATE ON dbo.tblStaffelgruppe TO janus_connect;
GRANT SELECT, INSERT, UPDATE ON dbo.tblStaffelpreise TO janus_connect;
GRANT SELECT, INSERT, UPDATE ON dbo.tblLinearStaffel TO janus_connect;
GRANT SELECT, INSERT, UPDATE ON dbo.tblZeittarife TO janus_connect;
GRANT SELECT, INSERT, UPDATE ON dbo.tblZeittarifeZonen TO janus_connect;
GRANT SELECT, INSERT, UPDATE ON dbo.tblBereichsStaffel TO janus_connect;
GRANT SELECT, INSERT, UPDATE ON dbo.tblBereichsStaffelPreise TO janus_connect;
GRANT SELECT, INSERT, UPDATE ON dbo.tblBandbreiteStaffel TO janus_connect;
GRANT SELECT, INSERT, UPDATE ON dbo.tblBandbreiteStaffelPreise TO janus_connect;
GRANT SELECT, INSERT, UPDATE ON dbo.tblSMSZugaenge TO janus_connect;
GRANT SELECT ON dbo.tblMwstSchluessel TO janus_connect;
GRANT INSERT, UPDATE ON dbo.tblProdukt TO janus_connect;
GRANT UPDATE (datBezahlDatum, fBezahlterBetrag, boolBezahlt) ON dbo.tblRechnung TO janus_connect;
```

`accountings.dbo.tblDomains` ist der historische, von den Accounting-Anbindungen referenzierte Domainbestand und benötigt nur `SELECT`. Er ist nicht mit `domains.dbo.tblDomains` zu verwechseln.

Für die separate Datenbank `domains`:

```sql
USE domains;
GO

GRANT SELECT, INSERT, UPDATE ON dbo.tblDomainKonditionen TO janus_connect;
GRANT SELECT, INSERT ON dbo.tblAllgemeineDomain TO janus_connect;
GRANT SELECT, INSERT ON dbo.tblDomains TO janus_connect;
GRANT SELECT, INSERT, UPDATE ON dbo.tblDomainKonditionenRabatte TO janus_connect;
GRANT UPDATE (datRegistriertAm, UMSTELLUNGintKundenID) ON dbo.tblDomains TO janus_connect;
GRANT SELECT ON dbo.tblDomainAuftrag TO janus_connect;
GRANT SELECT ON dbo.tblNameserver TO janus_connect;
GRANT SELECT, INSERT, UPDATE, DELETE ON dbo.tblDomainEintraege TO janus_connect;
```

DELETE bleibt grundsätzlich gesperrt, sofern es nicht fachlich ausdrücklich benötigt und entschieden wurde.

**Hinweis:** Das Wiki wird parallel zu technischer Dokumentation und Benutzerhandbuch fortgeschrieben. Neue bestätigte Access-Abfragen, direkte SQL-Abfragen und Rechteänderungen werden hier mit kurzer Erklärung ergänzt. ORM-intern erzeugte Einzelabfragen werden nicht automatisch als Vollprotokoll aufgenommen, sofern sie keine eigenständige fachliche Bedeutung haben.


## Produktivmigration Minerva → Cardea

**Zweck:** Reproduzierbare SQL-Prüfungen für den finalen Umzug von `accountings`, `domains` und `topsnetdb_safe`. Alle Ausgaben werden mit Servername, Zeitpunkt und Phase (Minerva vor Sicherung / Cardea nach Restore) im Migrationsprotokoll gespeichert. Referenzzählungen unmittelbar vor dem Cutover sind maßgeblich.

### 1. Server- und Datenbankstatus

Auf Minerva vor der finalen Sicherung und auf Cardea nach dem Restore ausführen:

```sql
SELECT @@SERVERNAME AS server_name,
       SYSDATETIME() AS pruefzeitpunkt,
       d.name,
       d.state_desc,
       d.recovery_model_desc,
       d.compatibility_level,
       SUSER_SNAME(d.owner_sid) AS database_owner
FROM sys.databases AS d
WHERE d.name IN (N'accountings', N'domains', N'topsnetdb_safe')
ORDER BY d.name;

SELECT DB_NAME(database_id) AS database_name,
       type_desc,
       name AS logical_file_name,
       physical_name,
       CAST(size * 8.0 / 1024 AS decimal(18,2)) AS size_mb
FROM sys.master_files
WHERE DB_NAME(database_id) IN (N'accountings', N'domains', N'topsnetdb_safe')
ORDER BY database_name, type_desc;
```

### 2. Referenzzählungen

Diese Abfragen sind das Mindestset. Vor dem Cutover werden weitere für die dann produktiven Module relevante Tabellen ergänzt.

```sql
SELECT N'topsnetdb_safe.dbo.tblKunde' AS objekt, COUNT_BIG(*) AS anzahl
FROM topsnetdb_safe.dbo.tblKunde
UNION ALL SELECT N'topsnetdb_safe.dbo.tblAnsprechpartner', COUNT_BIG(*)
FROM topsnetdb_safe.dbo.tblAnsprechpartner
UNION ALL SELECT N'topsnetdb_safe.dbo.tblProjekt', COUNT_BIG(*)
FROM topsnetdb_safe.dbo.tblProjekt
UNION ALL SELECT N'accountings.dbo.tblAuftrag', COUNT_BIG(*)
FROM accountings.dbo.tblAuftrag
UNION ALL SELECT N'accountings.dbo.tblAuftragPos', COUNT_BIG(*)
FROM accountings.dbo.tblAuftragPos
UNION ALL SELECT N'accountings.dbo.tblRechnung', COUNT_BIG(*)
FROM accountings.dbo.tblRechnung
UNION ALL SELECT N'accountings.dbo.tblAnbindungen', COUNT_BIG(*)
FROM accountings.dbo.tblAnbindungen
UNION ALL SELECT N'domains.dbo.tblDomains', COUNT_BIG(*)
FROM domains.dbo.tblDomains;
```

Ergänzend sind pro zentraler Tabelle `MIN`/`MAX` der Primärschlüssel und fachlich relevanter Datumsfelder zu protokollieren. Stimmen reine Zählwerte überein, ersetzt das noch keinen Anwendungstest.

### 3. Benutzer und Login-Mapping auf Cardea

Der Server-Login muss auf Cardea bereits kontrolliert angelegt sein. Kennwörter werden weder im Skript noch in Git dokumentiert. Nach jedem Restore:

```sql
USE accountings;
IF USER_ID(N'janus_connect') IS NULL
    CREATE USER janus_connect FOR LOGIN janus_connect;
ELSE
    ALTER USER janus_connect WITH LOGIN = janus_connect;

USE domains;
IF USER_ID(N'janus_connect') IS NULL
    CREATE USER janus_connect FOR LOGIN janus_connect;
ELSE
    ALTER USER janus_connect WITH LOGIN = janus_connect;

USE topsnetdb_safe;
IF USER_ID(N'janus_connect') IS NULL
    CREATE USER janus_connect FOR LOGIN janus_connect;
ELSE
    ALTER USER janus_connect WITH LOGIN = janus_connect;
```

Kontrolle verwaister SQL-Benutzer je Datenbank:

```sql
SELECT dp.name AS database_user, dp.type_desc
FROM sys.database_principals AS dp
LEFT JOIN sys.server_principals AS sp ON sp.sid = dp.sid
WHERE dp.authentication_type_desc = N'INSTANCE'
  AND dp.principal_id > 4
  AND sp.sid IS NULL
ORDER BY dp.name;
```

Die dokumentierten Minimalrechte von `janus_connect` sind anschließend stichprobenartig mit `HAS_PERMS_BY_NAME` beziehungsweise über `sys.database_permissions` zu kontrollieren. Ein Restore darf nicht zum Anlass genommen werden, pauschal `db_owner` zu vergeben.

### 4. Konsistenzprüfung auf Cardea

```sql
DBCC CHECKDB (N'accountings') WITH NO_INFOMSGS, ALL_ERRORMSGS;
DBCC CHECKDB (N'domains') WITH NO_INFOMSGS, ALL_ERRORMSGS;
DBCC CHECKDB (N'topsnetdb_safe') WITH NO_INFOMSGS, ALL_ERRORMSGS;
```

Alle drei Prüfungen müssen ohne Konsistenzfehler enden. Der eingerichtete SQL-Agent-Job `DB-Wartung - CHECKDB` führt diese Prüfung danach wöchentlich sonntags um 03:00 Uhr aus; der manuelle Funktionstest am 17.09.2026 war erfolgreich. Reguläre Sicherungen erfolgen über Veeam.

### 5. Umschaltung und Rollback

Vor der finalen Sicherung müssen sämtliche schreibenden Anwendungen auf Minerva beendet sein. Nach Restore, Mapping, CHECKDB und Datenvergleich werden zuerst lesende Smoke-Tests gegen Cardea ausgeführt. Die Schreibfreigabe erfolgt erst nach fachlicher Abnahme und ist der letzte Cutover-Schritt.

Falls vor der Schreibfreigabe ein Abbruch nötig ist, werden die Verbindungen auf Minerva zurückgestellt. Nach einer Schreibfreigabe auf Cardea ist ein einfaches Zurückschalten unzulässig: Die inzwischen auf Cardea entstandenen Änderungen müssen zuerst gesichert, verglichen und übernommen oder bewusst verworfen werden. Minerva bleibt bis zum Ende der Abnahme unverändert als Rückfallstand erhalten.

### Wartungsplan `cleanup_alte_accountingdaten`

Der auf SQL Server bestehende Wartungsplan `cleanup_alte_accountingdaten` soll beibehalten werden. Er löscht monatlich Roh-/Zwischendaten aus `accountings.dbo.tblAccountingFromPort`, `accountings.dbo.tblAccountingNetzeTageswerte` und `accountings.dbo.tblAccountingIntervall`, sobald deren jeweiliger Zeitstempel älter als zwei Jahre ist. Diese drei Tabellen werden von der migrierten Laravel-Webapp derzeit nicht gelesen. Der Zweck ist daher Datenmengenbegrenzung bei historischen Accounting-Rohdaten; die fachlich für Rechnungsläufe verwendeten Tabellen wie `tblAnbindungAuswertung`, `tblAuftragPosBerechnet` oder `tblRechnung` sind davon nicht betroffen.

Die bisherige Jobdefinition hat einen Fehler bei der Protokollierung: `@@ROWCOUNT` wird erst nach allen drei `DELETE`-Statements ausgewertet. Damit erhalten alle drei Zählvariablen denselben Wert des zuletzt ausgeführten Löschvorgangs. In der verbesserten Fassung muss `@@ROWCOUNT` unmittelbar nach jedem einzelnen `DELETE` in die zugehörige Variable übernommen werden. Zusätzlich soll ein gemeinsamer fester Stichtag (`DATEADD(YEAR,-2,GETDATE())`) einmalig zu Beginn berechnet werden, damit alle drei Tabellen exakt denselben Aufbewahrungszeitpunkt verwenden.

Empfohlene weitere Absicherung: Ausführung in `TRY/CATCH`, Fehler-Mail bei Abbruch und bei größeren Löschmengen optionales Löschen in Batches, um Transaktionslog und Sperrzeiten zu begrenzen. Vor einer Änderung der Aufbewahrungsdauer muss geprüft werden, ob externe Auswertungen außerhalb der Webapp auf Rohdaten älter als zwei Jahre zugreifen. Die Webapp selbst enthält aktuell keine Referenz auf die drei Tabellen.

Empfohlene Zählweise im Job:

```sql
DECLARE @cutoff datetime = DATEADD(YEAR,-2,GETDATE());
DECLARE @deletedCountFromPort int = 0,
        @deletedCountNetzeTageswerte int = 0,
        @deletedCountIntervall int = 0;

DELETE FROM dbo.tblAccountingFromPort WHERE dateDatum < @cutoff;
SET @deletedCountFromPort = @@ROWCOUNT;

DELETE FROM dbo.tblAccountingNetzeTageswerte WHERE dateBegin < @cutoff;
SET @deletedCountNetzeTageswerte = @@ROWCOUNT;

DELETE FROM dbo.tblAccountingIntervall WHERE dateEnddatum < @cutoff;
SET @deletedCountIntervall = @@ROWCOUNT;
```

### Testserver: ergänzende Performance-Indizes

Nach dem Datenbank-Refresh vom 17.09.2026 wurden auf dem SQL-Server-2019-Teststand gezielt Indizes für die tatsächlichen Webapp-/Rechnungstool-Abfragen ergänzt. Geeignete Rückgabespalten sind jeweils als INCLUDE-Spalten hinterlegt. Es wurden keine Altindizes gelöscht.

- `tblAuftragPosBerechnet (intAufPosID, BerechnetZum)` mit INCLUDE `fBetrag, intRechnungIntID`: beschleunigt die sehr häufige Prüfung, ob eine konkrete Auftragsposition für einen Berechnungstermin bereits verarbeitet wurde, und liefert dabei die für Paritätsprüfung bzw. Rechnungsbezug benötigten Werte ohne zusätzlichen Lookup.
- `tblAnbindungAuswertung (intAnbindungID, intJahr, intMonat)` mit INCLUDE `decGesamt, decMBin, decMBout, intVerbindungsdauerInSec`: passt zu den Monatsabfragen der Staffel-, Linear-, Bandbreiten- und Accountingberechnung. Dadurch kann SQL Server direkt über Anbindung plus Jahr/Monat suchen, statt erst über getrennte Indizes oder größere Mengen zu filtern.
- `tblAccountingKonto (intAufPosID, intRechnungsJahr, intRechnungsMonat)` mit INCLUDE `intMB`: unterstützt die Vor-/Nachberechnung einer Auftragsposition für einen bestimmten Rechnungsmonat und vermeidet Vollscans der Accounting-Kontotabelle.
- `tblBandbreiteStaffelPreise (intStaffelGruppenID, intMenge)` mit INCLUDE `fVkPreis`: unterstützt die Suche nach der kleinsten passenden Bandbreiten-Preisgrenze für eine Staffelgruppe (`intMenge >= Nutzung` mit `ORDER BY intMenge`).
- `tblAnbindungen (intAuftragsPos, intTyp, boolAbrechenbar)` mit INCLUDE `intAnbindungReferenz, dateAbrechenbarStart, dateAbrechenbarEnde`: passt zu den wiederkehrenden Abfragen der Webapp nach Anbindungen einer Auftragsposition, technischen Typen und aktiver Abrechenbarkeit; die Referenz- und Laufzeitfelder stehen direkt im Index zur Verfügung.
- `tblStaffelpreise (intStaffelgruppeID, intMenge)` mit INCLUDE `intvkpreis`: unterstützt die Auswahl der nächsten passenden Preisstufe innerhalb einer Staffelgruppe und bildet damit die `>= Menge`-/`ORDER BY intMenge`-Abfrage der Berechnungsengine direkt ab.
- `domains.dbo.tblDomainKonditionenRabatte (intAuftragsPosID)` mit INCLUDE `fRabattEinrichtung, fRabattRegulaer`: unterstützt das Nachschlagen des Domain-Rabattdatensatzes zu einer konkreten Auftragsposition ohne Tabellenscan und deckt gleichzeitig die beiden gelesenen Rabattwerte ab.

Ein read-only Gesamttestlauf für August 2026 blieb fachlich unverändert bei 103 Kunden, 132 Aufträgen, 41 fakturierbar, 83 ohne neue Berechnung und 8 blockiert. Die gemessene Laufzeit lag vor den Ergänzungen bei etwa 3,15–4,33 s und danach bei etwa 3,09–3,21 s; der Gesamtlauf profitiert damit nur moderat, da ein großer Teil der Laufzeit aus vielen Einzelabfragen und Anwendungslogik besteht. Die Indizes sind primär für gezielte Seeks, stabile Laufzeiten und wachsende Datenmengen vorgesehen.
