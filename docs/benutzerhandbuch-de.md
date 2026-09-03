# DHL Deutschepost 3.2.9 — Benutzerhandbuch

## Hier beginnen

DHL Deutschepost verbindet PrestaShop-Bestellungen mit dem DHL-Paketversand für Geschäftskunden und der Deutschen Post INTERNETMARKE. Das Modul erzeugt Versandlabels im Back Office, speichert Sendungsnummern, unterstützt die Stapelerstellung, Manifeste und vorgesehene Retourenabläufe und kann die Eingabe von Packstationen und Postfilialen im Checkout erleichtern.

Das Modul schließt keinen DHL-Vertrag ab, schaltet keine Produkte frei, berechnet keine Live-Versandpreise im Checkout und entscheidet nicht über Ihren Vertragsumfang. Beschaffen Sie vor der Einrichtung des Live-Betriebs die nachfolgend beschriebenen Vertragsdaten bei DHL.

## Was Sie vor der Einrichtung benötigen

| Benötigte Angabe | Bezugsquelle | Verwendungszweck |
| --- | --- | --- |
| DHL-Geschäftskundenvertrag | Ihre DHL-Vertriebsbetreuung | DHL-Paketversand im Live-Betrieb |
| GKP-Benutzername und Passwort | Post & DHL Geschäftskundenportal (GKP) | Authentifizierung im Live-Betrieb |
| EKP | Vertragsdaten im GKP oder DHL-Vertragsunterlagen | Kennzeichnet das Kundenkonto |
| Abrechnungsnummern | Vertragspositionen im GKP oder Vertragsunterlagen | Kennzeichnen Verfahren/Produkt und Teilnahme |
| Freischaltung für DHL Retoure | DHL-Vertrag | Retourenlabels, falls benötigt |
| Portokasse-Konto | Deutsche Post | INTERNETMARKE, falls benötigt |

Fehlt eine Angabe, wenden Sie sich an die für Ihren Vertrag zuständige DHL-Ansprechperson. Das Modul kann Vertragsnummern weder ermitteln noch freischalten.

## Erste Anmeldung im DHL-Geschäftskundenportal

1. Öffnen Sie das **Post & DHL Geschäftskundenportal** unter <https://geschaeftskunden.dhl.de/>.
2. Melden Sie sich mit dem persönlichen Benutzernamen aus den Vertragsunterlagen und dem Initialpasswort beziehungsweise dem Link zum Zurücksetzen des Passworts an.
3. Schließen Sie die vom Portal verlangte Aktivierung oder Passwortänderung ab.
4. Sind keine Zugangsdaten angekommen, verwenden Sie **Passwort vergessen** oder wenden Sie sich an den Kontoadministrator beziehungsweise Ihre DHL-Betreuung. Der erste Portalzugang wird üblicherweise nach Bearbeitung der Geschäftskundeneinrichtung versendet.
5. Für eine produktive API-Anbindung empfiehlt DHL einen eigenen **Geschäftskunden-Systembenutzer**. Dieser kann angelegt oder angefordert werden, sobald ein persönlicher Administrator Zugriff auf das GKP hat.

Ein Systembenutzer ist für die API-Authentifizierung vorgesehen und kann sich nicht an der GKP-Weboberfläche anmelden. Behalten Sie deshalb mindestens ein persönliches Administratorkonto. Tragen Sie keine Client-ID und kein Client-Secret aus dem DHL Developer Portal ein: Die aktuelle Moduloberfläche hat dafür keine Felder und verwendet im Live-Betrieb die hinterlegten GKP-Zugangsdaten.

## EKP, Produkt- und Teilnahmenummer finden

Öffnen Sie im GKP den Vertragsbereich – üblicherweise **Vertragsdaten > Vertragspositionen** – und suchen Sie die **Abrechnungsnummer** jeder DHL-Leistung, die der Shop verwenden soll. Die genaue Navigation kann sich ändern; maßgeblich ist die der Vertragsposition zugeordnete Abrechnungsnummer.

Eine DHL-Paket-Abrechnungsnummer hat 14 Zeichen:

`1234567890 01 01`

| Bestandteil | Länge | Beispiel | Eingabe im Modul |
| --- | --- | --- | --- |
| EKP | 10 Zeichen | `1234567890` | Feld **EKP** |
| Verfahren/Produkt | 2 Zeichen | `01` | Passendes **DHL-Produkt** auswählen |
| Teilnahme | 2 Zeichen | `01` | Beim Produkt als **Teilnahme** eintragen |

Tragen Sie nicht die vollständige 14-stellige Abrechnungsnummer in das Teilnahmefeld ein. Auch der mittlere Verfahrenscode gehört nicht in dieses Feld.

Das Modul bietet derzeit folgende Produkte an:

| Verfahren | Produkt im Modul |
| --- | --- |
| `01` | DHL Paket |
| `53` | DHL Paket International |
| `54` | DHL Europaket |
| `62` | DHL Kleinpaket |
| `66` | Warenpost International |

Legen Sie nur Produkte an, die in Ihrem Vertrag vorhanden sind. Ein Produkt kann mehrere Teilnahmen besitzen; richten Sie nur die tatsächlich benötigten Kombinationen ein. DHL kann numerische oder alphanumerische Teilnahmen vergeben. Das separate Modulfeld **Retouren-Teilnahme** akzeptiert in dieser Version allerdings genau zwei Ziffern.

Verwenden Sie für Retouren die Teilnahme der passenden DHL-Retoure-Vertragsposition. `01` ist häufig, aber nicht garantiert. Die alte Moduldokumentation beschrieb separate Zugangsdaten zum Retourenportal und eine Portal-ID. Die aktuelle Version besitzt diese Felder nicht und verwendet dieses frühere Anmeldeverfahren nicht.

## Voraussetzungen und Kompatibilität

- PrestaShop: Das Modul deklariert Version 1.6 als Minimum und die laufende PrestaShop-Version als Maximum. Das Changelog enthält Anpassungen für PrestaShop 8 und 9.
- PHP: Ein formaler Bereich ist nicht deklariert. Im Changelog stehen Korrekturen für PHP 7.2 und 8.4. Der aktuelle Code ist wegen der Signatur von `SoapClient::__doRequest()` nicht mit PHP 8.5 kompatibel; verwenden Sie bis zu einer Anpassung eine unterstützte Version wie PHP 8.4.
- Laufzeit: Ausgehendes HTTPS, cURL, JSON und mbstring werden verwendet. Die Modulverzeichnisse `logs`, `pdfs` und `data` müssen beschreibbar sein.
- DHL: Als Absenderland ist derzeit nur Deutschland (`DE`) verfügbar; die Versand-API-Version ist auf `2.1` festgelegt.
- Konten: Für Live-DHL-Labels werden GKP-Zugangsdaten und freigeschaltete Produkte benötigt. INTERNETMARKE erfordert ein Portokasse-Konto.

Sichern Sie vor der Installation Dateien und Datenbank, testen Sie in einer Staging-Umgebung und prüfen Sie ausgehende HTTPS-Verbindungen. Legen Sie fest, welche vorhandenen PrestaShop-Versanddienste welchen Vertragsprodukten entsprechen. Wählen Sie in Multishop vor dem Speichern den richtigen Shopkontext.

## Installieren oder aktualisieren

### Neuinstallation

1. Wählen Sie unter **Module > Module Manager** die Funktion **Modul hochladen** und laden Sie das Release-ZIP hoch.
2. Installieren Sie **DHL Deutschepost** und öffnen Sie **Konfigurieren**.
3. Führen Sie anschließend die unten beschriebene Erstanbindung durch.

### Aktualisierung ohne Verlust der Einstellungen

Laden Sie das neue ZIP über den Module Manager hoch oder ersetzen Sie das vorhandene Verzeichnis `dhldp` vollständig durch die neue Version. Deinstallieren Sie das Modul vorher nicht. Führen Sie die PrestaShop-Modulaktualisierung aus, wenn sie angeboten wird. Vorhandene Konfigurationsschlüssel und Daten bleiben über den normalen Upgrade-Pfad erhalten.

Leeren Sie nach dem Update den PrestaShop- und Browsercache nur dann, wenn weiterhin alte Templates, Übersetzungen, CSS- oder JavaScript-Dateien angezeigt werden.

## DHL zum ersten Mal verbinden

1. Öffnen Sie in der Modulkonfiguration **DHL Einstellungen**.
2. Wählen Sie **Sandbox**, um den Ablauf ohne Live-Vertrag zu testen. Das Modul verwendet dafür mitgelieferte DHL-Sandbox-Zugangsdaten. Verwenden Sie **Live** erst für den Produktivbetrieb.
3. Tragen Sie im Live-Modus den GKP-/API-Benutzernamen, sein Passwort und die zehnstellige EKP ein und speichern Sie. Beim Speichern wird das Konto geprüft. Später angezeigte Sternchen bedeuten lediglich, dass ein Passwort gespeichert ist.
4. Fügen Sie unter **DHL Produkte** jedes Vertragsprodukt hinzu. Wählen Sie das Produkt anhand der mittleren zwei Zeichen der Abrechnungsnummer und tragen Sie die letzten zwei Zeichen als Teilnahme ein.
5. Ordnen Sie unter **Versanddienste** jeden relevanten PrestaShop-Versanddienst der richtigen Produkt-/Teilnahmekombination zu. DHL-Aktionen erscheinen bei einer Bestellung nur bei vorhandener Zuordnung.
6. Hinterlegen Sie die Absenderadresse. Straße und Hausnummer gehören in getrennte Felder. Alternativ wählen Sie eine GKP-Absenderreferenz und übernehmen diese exakt.
7. Wählen Sie das passende Labelformat für den installierten Drucker. Lassen Sie für den ersten Test automatische Statuswechsel, automatische Warnungsannahme, sofortige Retouren und Zusatzservices ausgeschaltet.
8. Speichern Sie und erzeugen Sie ein Label für eine Testbestellung.

Die Sandbox prüft den Modulablauf, nicht den Umfang Ihres Live-Vertrags. Erzeugen Sie vor dem Regelbetrieb zusätzlich ein kontrolliertes Testlabel im Live-Modus.

## Das erste DHL-Label erzeugen

1. Erstellen oder öffnen Sie eine Testbestellung mit einem in **DHL Einstellungen** zugeordneten Versanddienst.
2. Wählen Sie im DHL-Bereich der Bestellung **Label erzeugen**.
3. Prüfen Sie Empfängerstraße und Hausnummer, Postleitzahl, Land, Gewicht und Abmessungen. Korrigieren Sie die Anschrift vor einem neuen Versuch über **Lieferadresse aktualisieren**.
4. Prüfen Sie bei internationalen Sendungen für jede Zollposition Beschreibung, Wert, Ursprungsland und Zolltarifnummer.
5. Wählen Sie ausschließlich Services, die Produkt und Vertrag unterstützen, und senden Sie die Anfrage ab.
6. Öffnen Sie das PDF und kontrollieren Sie Absender, Empfänger, Produkt, Format und Sendungsnummer.
7. Prüfen Sie, ob die Sendungsnummer in der Bestellung steht und konfigurierte Status- oder E-Mail-Aktionen genau einmal ausgeführt wurden.

Kommt die Empfängeranschrift als gemeinsame Straßenzeile an, trennen Sie die Hausnummer in das dafür vorgesehene Feld. Eine fehlende Trennung ist ein häufiger Ablehnungsgrund.

## Labels als Stapel erzeugen

1. Öffnen Sie die PrestaShop-Bestellliste.
2. Markieren Sie Bestellungen mit gültig zugeordneten DHL-Versanddiensten.
3. Wählen Sie die Stapelaktion **DHL-Labels erzeugen**.
4. Prüfen Sie Fehler einzeln; eine ungültige Adresse, fehlendes Gewicht oder ein nicht unterstützter Service kann nur die jeweilige Bestellung betreffen.
5. Verwenden Sie bei Bedarf **Letzte Labels drucken**, um den zuletzt erzeugten Stapel erneut abzurufen.

Beginnen Sie mit wenigen Bestellungen. Die Stapelverarbeitung ersetzt keine gültigen Anschriften, Gewichte und Zolldaten.

## Häufige Probleme beim ersten Label

| Symptom | Wahrscheinliche Ursache | Prüfung und Lösung |
| --- | --- | --- |
| Kontodaten werden beim Speichern abgelehnt | Falscher Live-Benutzer, Passwort oder EKP; Benutzer gesperrt | Persönlichen GKP-Zugang prüfen, API-/Systembenutzer separat kontrollieren, gegebenenfalls Passwort zurücksetzen und EKP mit den Vertragsdaten vergleichen. |
| Anmeldung im Portal funktioniert, im Modul aber nicht | Persönlicher Portalbenutzer und API-Systembenutzer wurden verwechselt oder das Passwort ist abgelaufen | Die für die API vorgesehenen Daten verwenden. Ein Systembenutzer kann sich nicht an der Portaloberfläche anmelden. |
| Keine DHL-Aktion in der Bestellung | Der PrestaShop-Versanddienst ist im aktuellen Shopkontext nicht zugeordnet | Zuordnung für Versanddienst und Shop neu speichern. |
| Produkt oder Teilnahme wird abgelehnt | Falscher Teil der Abrechnungsnummer oder Produkt nicht im Vertrag | Bei 14 Zeichen Position 11–12 als Produkt wählen und Position 13–14 als Teilnahme eintragen. |
| Anschrift wird abgelehnt | Hausnummer fehlt/steht in der Straße, PLZ oder Land ist ungültig | Straße und Hausnummer trennen und Ziellandformat prüfen. |
| Gewicht oder Abmessungen werden abgelehnt | Wert leer, null, falsch umgerechnet oder über Produktgrenze | Produktgewichte, Verpackungsgewicht und Umrechnung prüfen (`1` für kg, `0,001` für Gramm). |
| Exportsendung schlägt fehl | Beschreibung, Wert, Ursprung oder Zolltarifnummer fehlt | Jede Zollposition vervollständigen; das Modul akzeptiert 6-, 8- oder 10-stellige Zolltarifnummern. |
| Zusatzservice ist nicht verfügbar | Produkt, Ziel oder Vertrag unterstützt ihn nicht | Service entfernen oder Vertragsposition durch DHL bestätigen lassen. |
| PDF wird nicht gespeichert | `pdfs` ist nicht beschreibbar oder API-Anfrage fehlgeschlagen | Rechte und Speicherplatz prüfen und das maskierte API-Log vorübergehend aktivieren. |
| Alte Einstellungen bleiben sichtbar | Falscher Multishop-Kontext oder Cache | Richtigen Kontext wählen, erneut speichern und PrestaShop-/Browsercache leeren. |

## Einstellungen, die eine bewusste Entscheidung erfordern

Die übrigen Einstellungen sollten nach ihrer Wirkung und nicht ungeprüft Feld für Feld eingerichtet werden:

- **Gewicht:** Produktgewichte nur berechnen lassen, wenn sie gepflegt sind. Umrechnung `1` für Kilogramm oder `0,001` für Gramm verwenden und realistisches Verpackungsgewicht addieren.
- **Drucker:** Ein zum Drucker passendes Format wählen. `100x70mm` ist nur für DHL Kleinpaket und Warenpost International vorgesehen.
- **Bestellstatus und E-Mail:** Zunächst keinen automatischen Statuswechsel und keine Transitmail verwenden. Erst nach Prüfung auf doppelte PrestaShop-Nachrichten aktivieren.
- **Warnungen:** Automatische Annahme zunächst ausgeschaltet lassen, damit Mitarbeitende DHL-Warnungen sehen.
- **Zusatzservices:** Filial-Routing, GoGreen, GoGreen Plus, Alterssichtprüfung, Premium und Retourenoptionen hängen von Produkt, Ziel und Vertrag ab und können Zusatzkosten verursachen.
- **Datenschutz-Zustimmung:** Ist die Abfrage deaktiviert, übermittelt das Modul E-Mail und Telefon standardmäßig an DHL. Rechtsgrundlage und Kundeninformation mit der Datenschutzberatung des Shops klären.
- **Packstation/Postfiliale:** Die Checkout-Hilfen funktionieren auch ohne Karte. Google Maps ist optional und benötigt einen separat beschränkten API-Schlüssel.
- **Retouren:** Erweiterte Retourenverwaltung nur zusammen mit PrestaShop-Warenrücksendungen und nach Prüfung der erlaubten Rücksendeländer aktivieren. Sofortiger Labelversand ist standardmäßig aus.
- **Nachnahme:** Kontoinhaber und korrekte IBAN/BIC nur bei vertraglicher Nachnahmeverwendung eintragen.
- **Protokolle:** DHL-/DP-Logs nur zur Diagnose aktivieren und danach abschalten; Logs und PDFs werden nicht automatisch gelöscht.

## Deutsche Post INTERNETMARKE erstmals einrichten

Dieser Ablauf ist von DHL Paket getrennt.

1. Registrieren Sie sich bei der **Portokasse** oder melden Sie sich unter <https://portokasse.deutschepost.de/portokasse/> an. Bei einer Neuregistrierung kann ein per Brief versandter Aktivierungscode erforderlich sein.
2. Öffnen Sie **DHL DP Einstellungen** und tragen Sie Benutzername und Passwort der Portokasse/INTERNETMARKE ein.
3. Bei der ersten Verbindung kann die Portokasse eine dauerhafte Freigabe der Geschäftsanwendung verlangen. Prüfen Sie diese unter **Meine Daten > Geschäftsanwendungen**.
4. Rufen Sie die Seitenformate ab und aktualisieren Sie bei Bedarf die PPL-Produktliste.
5. Ordnen Sie nur die für Deutsche Post bestimmten PrestaShop-Versanddienste zu, wählen Sie Standardprodukt und Ausgabeformat (`pdf` oder `png`) und hinterlegen Sie den Absender.
6. Wählen Sie bei PDF-Bögen Seitenformat, Startseite, Zeile und Spalte und drucken Sie vor der Stapelverarbeitung eine Testseite.

Schlägt die Anmeldung fehl, testen Sie den Portokasse-Login direkt und verwenden Sie dort die Passwort-Zurücksetzung. Ein im Modul sichtbares Deutsche-Post-Produkt garantiert weder die Kontoberechtigung noch den aktuellen Preis.

## Multishop

Die Einstellungsseiten unterstützen die Kontexte alle Shops, Shopgruppe und einzelner Shop sowie die normale PrestaShop-Vererbung. Speichern Sie allgemeine Defaults im Alle-Shops-Kontext, Gruppenwerte nur bei echter gemeinsamer Nutzung und abweichende Zugangsdaten, Absender und Versanddienstzuordnungen je Einzelshop.

Operative Einstellungen werden im Allgemeinen mit dem Shop der Bestellung gelesen. Die sechs Modultabellen besitzen jedoch kein direktes `id_shop`; Produktzollangaben und Deutsche-Post-Produktliste werden geteilt, PPL-Version und Seitenformatliste sind global. Manifest- und Informationscontroller verlangen bei aktivem Multishop einen Einzelshop-Kontext. Erzeugen Sie in jedem produktiven Shop ein Testlabel.

## Gespeicherte Daten, Datenschutz und externe Dienste

Das Modul speichert Zugangsdaten und Einstellungen in der PrestaShop-Konfiguration, Label-, Paket-, Zustimmungs-, Zoll- und INTERNETMARKE-Datensätze in sechs `dhldp_*`-Tabellen, erzeugte Dateien in `pdfs`, optionale Logs in `logs` und die Deutsche-Post-Produktliste in `data/ppl.csv`.

Bei der Sendungserstellung können je nach Service Absender- und Empfängernamen, Anschriften, Inhalte, Werte, Referenzen, E-Mail und Telefon an DHL oder Deutsche Post übertragen werden. Optionale Integrationen sind DHL Location Finder, Google Maps für die Kartenansicht, `prestamodule.silberserver.de` für das PPL-CSV-Update, der Shop-Mailversand und eine konfigurierte HP-ePrint-Adresse. Die lokale Dokumentation selbst benötigt keine externen Ressourcen.

## Grenzen, Cache und Deinstallation

- Als DHL-Absenderland wird derzeit nur Deutschland unterstützt; die API-Version ist auf 2.1 festgelegt.
- Es gibt keine Live-Tarifberechnung im Checkout und keinen Hintergrund-Worker. Die meisten API-Aufrufe laufen in der Webanfrage.
- Produkt-/Serviceverfügbarkeit und Preise werden durch externe Konten und APIs bestimmt.
- `cron_track.php` aktualisiert Trackingdaten mit dem geheimen Modulschlüssel; es gibt keinen Konsolenbefehl. Schützen Sie die Cron-URL.
- Der Österreich-Absenderablauf, die separate Retourenportal-Anmeldung und die manuelle alte Tracking-URL aus der früheren Anleitung gelten für diese Version nicht.

Für normale Labelerstellung muss kein Cache geleert werden. Leeren Sie ihn nach Updates nur bei weiterhin sichtbaren alten Assets oder Übersetzungen.

Die Deinstallation entfernt Modultabs und eigene Hooks, löscht aber absichtlich weder die sechs Tabellen noch Konfigurationswerte, Labels, Logs oder Produktliste. Sichern und löschen Sie diese Elemente nur dann manuell, wenn eine vollständige Entfernung beabsichtigt ist.

## Checkliste vor dem Produktivstart

- Die Live-Kontoprüfung ist mit dem vorgesehenen API-Benutzer und der EKP erfolgreich.
- Jeder zugeordnete Versanddienst verweist auf ein Vertragsprodukt und die richtige Teilnahme.
- Absender, Trennung von Straße/Hausnummer, Gewichtsumrechnung und Druckformat sind geprüft.
- Ein Inlandlabel, ein benötigtes Exportlabel und gegebenenfalls der Retourenablauf wurden getrennt getestet.
- Statuswechsel, Kundenmails und Datenschutz-Zustimmung entsprechen dem Shopprozess.
- Jeder Multishop-Kontext besitzt einen eigenen kontrollierten Test.
- Diagnoseprotokollierung ist nach der Abnahme ausgeschaltet.

