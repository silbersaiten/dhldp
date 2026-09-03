# DHL Deutschepost 3.2.9 — Modulbeschreibung

## Kurzbeschreibung

DHL-Paket- und Deutsche-Post-INTERNETMARKE-Labels aus PrestaShop-Bestellungen erzeugen, Sendungsdaten übernehmen und unterstützte Zoll-, Manifest- und Retourenabläufe im Back Office verwalten.

## Ausführliche Beschreibung

DHL Deutschepost verknüpft ausgewählte PrestaShop-Versanddienste mit vertraglichen DHL- und Deutsche-Post-Produkten. Mitarbeiter bereiten Sendungsdaten direkt an der Bestellung vor, erzeugen und laden unterstützte Labels herunter und übernehmen die Sendungsnummer in den Bestellcarrier. Optional ändert das Modul den Bestellstatus oder sendet die enthaltene Unterwegs-Mail. Für DHL stehen Paketdefaults, Zusatzservices, Exportdaten und Retourenfunktionen bereit; der Deutsche-Post-Bereich steuert INTERNETMARKE-Produkte, PDF-/PNG-Ausgabe, Seitenposition, Manifest und Versandliste.

Damit reduziert das Modul die doppelte Eingabe zwischen Shop und Versandportal. Es ersetzt weder den Versandvertrag noch berechnet es Live-Checkoutpreise oder schaltet nicht verfügbare Leistungen frei.

## Zentrale Funktionen

- DHL Parcel Shipping API 2.1 für die aktuelle deutsche Absenderkonfiguration.
- Deutsche Post INTERNETMARKE per REST, Ausgabe als PDF oder PNG.
- Zuordnung von PrestaShop-Carriern zu DHL-/Deutsche-Post-Produkten.
- Einzel- und unterstützte DHL-Bulklabel-Abläufe.
- Speicherung von Sendungsnummern und geschützter Tracking-Cron.
- Optionale Statusänderung und Unterwegs-Mail.
- Produkt-Zolltarifnummer und Ursprungsland sowie Exportdaten.
- DHL-Zusatzservices, Format- und Paketdefaults.
- Packstation-/Postfilial-Unterstützung, optional mit Google Maps.
- Unterstützte DHL-Retouren, beigelegte Retourenlabels und RMA-Anbindung.
- Manifest- und Deutsche-Post-Versandlistenfunktionen, soweit Produkt/API dies zulassen.
- Kontextabhängige Multishop-Konfiguration und Aktivierung.
- Lokale Dokumentation in sechs Sprachen.

## Einsatz und typischer Ablauf

Das Modul eignet sich für Shops, die vertragliche DHL-Paket-Sendungen aus Deutschland abwickeln oder INTERNETMARKE-Produkte nutzen. Nach Auswahl des Shop-Kontexts werden Konto, Absender, Produkte und Carrier-Zuordnungen eingerichtet. In einer geeigneten Bestellung prüft das Lager Sendungs-, Paket-, Export- und Servicedaten, erzeugt das Label und kontrolliert es. Das Modul speichert Label-/Trackingdaten und führt nur die ausgewählten Status- und Mailaktionen aus. Manifest, Retoure und Tracking werden separat genutzt, wenn Vertrag und Produkt sie unterstützen.

## Vorteile für Shopbetreiber

- Weniger erneute Eingabe von Bestell- und Adressdaten im Carrierportal.
- Labelreferenzen und Sendungsnummern bleiben mit der Bestellung verbunden.
- Kontrollierte Defaults für Produkte, Maße, Formate und Services.
- DHL Paket und Deutsche Post in einer Moduloberfläche.
- Explizite Optionen für Kontaktdateneinwilligung, Logs und Ausgabe.
- Offline-Handbuch und Modulbeschreibung in sechs Sprachen.

## Administration und Multishop

Die Bereiche **DHL settings**, **DHL DP settings**, **Information** und **DHL Manifest** trennen Konto, Produkte/Carrier, Labelverhalten, Zusatzservices, Retouren, Absender, Nachnahmedaten und INTERNETMARKE-Ausgabe. Der Schnellstart verlinkt lokale Dokumente, Modulkatalog, kostenpflichtigen Support und Support-E-Mail.

Alle-Shop-, Gruppen- und Einzelshop-Kontexte sind in den Einstellungscontrollern vorgesehen; die Aktivierung kann kontextbezogen geändert werden. Manche Daten bleiben global oder geteilt: DP-Seitenformate und PPL-Version, heruntergeladene Produktliste, Produktzollangaben und Tabellen ohne direkte Shop-Spalte. Manifest/Information verlangen im Multishop einen Einzelshop. Deshalb ist keine vollständige physische Datenisolation pro Shop zugesichert.

## Datenschutz und externe Dienste

Sendungsanfragen können Namen, Adressen, Inhalt/Werte, Referenzen, E-Mail und Telefon an DHL oder Deutsche Post übertragen. Bei ausgeschalteter Einwilligungsoption werden E-Mail und Telefon standardmäßig an DHL gesendet. Der Händler verantwortet Rechtsgrundlage, Hinweise und Aufbewahrung. Gespeichert werden Konfiguration/Zugang, Label-, Paket-, Tracking-, RMA- und Zolldaten, Deutsche-Post-Produkte/Preise, Dateien und optionale Logs. Eine Deinstallation löscht diese Daten nicht automatisch.

Zur Laufzeit können DHL-Versand-, Retouren-, Token-, Standort- und Trackingdienste, Deutsche Post INTERNETMARKE/Tracking, Silbersaiten für die PPL-CSV, optional Google Maps sowie Shop-Mail/HP ePrint kontaktiert werden. Die Dokumentation ist vollständig lokal.

## Kompatibilität und Grenzen

Der Code deklariert PrestaShop ab `1.6` bis zur laufenden Version; das Changelog nennt Arbeiten für PrestaShop 8 und 9. Ein formaler PHP-Bereich fehlt, dokumentiert sind Korrekturen für PHP 7.2 und 8.4. Der aktuelle Code ist wegen der Signatur von `SoapClient::__doRequest()` nicht mit PHP 8.5 kompatibel. Die konkrete Umgebung muss getestet werden.

- DHL-Absenderland nur Deutschland und API fest auf 2.1.
- Livekonten und vertraglich geeignete Produkte erforderlich.
- Keine Checkout-Tarifberechnung, Queue oder automatische Datei-/Logbereinigung.
- Verfügbarkeit, Preise, Laufzeiten und Serviceannahme werden von DHL/Deutsche Post bestimmt.
- Multishop-Einstellungen sind vorhanden, aber nicht alle Datenspeicher sind je Shop getrennt.

## Wichtigste Vorteile

- Integrierte Label- und Trackingbearbeitung in PrestaShop.
- DHL Paket und INTERNETMARKE in einem Modul.
- Carrier-/Produktzuordnung und Lagerdefaults.
- Zoll-, Zusatzservice-, Manifest- und Retourenoptionen.
- Nachvollziehbar dokumentiertes Multishop-Verhalten.
- Sechs lokale Dokumentationssprachen und direkte Supportwege.

## Drei sehr kurze Kartentexte

1. DHL-Paket- und INTERNETMARKE-Labels direkt aus PrestaShop-Bestellungen erstellen.
2. PrestaShop-Carrier mit DHL/Deutsche Post, Tracking, Zoll und Retouren verbinden.
3. Vertragliche DHL- und Deutsche-Post-Versandabläufe im PrestaShop Back Office verwalten.
