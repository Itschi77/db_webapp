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

## 34. Berechtigungs-Statements für `janus_connect`

**Zweck:** Dokumentiert die im Migrationsprojekt bewusst vergebenen Minimalrechte. Die Statements enthalten keine Zugangsdaten.

Für `accountings`:

```sql
GRANT SELECT, INSERT, UPDATE ON dbo.tblAnbindungen TO janus_connect;
GRANT SELECT, INSERT, UPDATE ON dbo.tblPort TO janus_connect;
GRANT SELECT, INSERT, UPDATE ON dbo.tblAnbindungDialin TO janus_connect;
GRANT SELECT, INSERT, UPDATE ON dbo.tblAnbindungDialinEinwahlnummern TO janus_connect;
GRANT SELECT, INSERT, UPDATE ON dbo.tblAnbindungNetze TO janus_connect;
USE accountings;
GRANT SELECT ON dbo.tblAnbindungAuswertung TO janus_connect;
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

Für die separate Datenbank `domains`:

```sql
USE domains;
GRANT SELECT, INSERT, UPDATE ON dbo.tblDomainKonditionen TO janus_connect;
GRANT SELECT, INSERT ON dbo.tblAllgemeineDomain TO janus_connect;
GRANT SELECT, INSERT ON dbo.tblDomains TO janus_connect;
GRANT UPDATE (datRegistriertAm, UMSTELLUNGintKundenID) ON dbo.tblDomains TO janus_connect;
GRANT SELECT ON dbo.tblDomainAuftrag TO janus_connect;
GRANT SELECT ON dbo.tblNameserver TO janus_connect;
GRANT SELECT, INSERT, UPDATE, DELETE ON dbo.tblDomainEintraege TO janus_connect;
```

DELETE bleibt grundsätzlich gesperrt, sofern es nicht fachlich ausdrücklich benötigt und entschieden wurde.

**Hinweis:** Das Wiki wird parallel zu technischer Dokumentation und Benutzerhandbuch fortgeschrieben. Neue bestätigte Access-Abfragen, direkte SQL-Abfragen und Rechteänderungen werden hier mit kurzer Erklärung ergänzt. ORM-intern erzeugte Einzelabfragen werden nicht automatisch als Vollprotokoll aufgenommen, sofern sie keine eigenständige fachliche Bedeutung haben.
