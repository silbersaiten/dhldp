# DHL Deutschepost 3.2.9 — Descrizione del modulo

## Descrizione breve

Crea etichette DHL Paket e Deutsche Post INTERNETMARKE dagli ordini PrestaShop e gestisce tracking, dogana, manifesti e resi supportati nel Back Office.

## Descrizione completa

DHL Deutschepost collega i corrieri PrestaShop ai prodotti DHL e Deutsche Post previsti dal contratto. Il personale prepara la spedizione nell'ordine, crea e scarica l'etichetta e registra il tracking. Facoltativamente il modulo cambia stato o invia l'e-mail di transito inclusa. DHL offre valori pacco, servizi aggiuntivi, export e resi; Deutsche Post controlla INTERNETMARKE, PDF/PNG, posizione pagina, manifesto e distinta.

Riduce il reinserimento di dati nel portale del vettore. Non sostituisce il contratto, non calcola tariffe live al checkout e non abilita servizi assenti dall'account.

## Funzioni principali

- DHL Parcel Shipping API 2.1 per mittente tedesco.
- Deutsche Post INTERNETMARKE REST in PDF o PNG.
- Associazione carrier–prodotto–participation.
- Etichette DHL singole e operazioni massive supportate.
- Tracking nell'ordine e cron protetto.
- Stato/e-mail opzionali, dogana prodotto ed export.
- Formati, dimensioni e servizi DHL predefiniti.
- Packstation/Postfiliale con Google Maps opzionale.
- Resi DHL e integrazione RMA supportata.
- Manifesti/distinte quando consentiti da prodotto/API.
- Configurazione e attivazione multishop contestuale.
- Documentazione locale in sei lingue.

## Impiego e flusso tipico

È adatto ai negozi che spediscono con DHL Paket contrattuale dalla Germania o usano INTERNETMARKE. L'amministratore seleziona il contesto e configura account, mittente, prodotti e corrieri. Il magazzino verifica la spedizione e crea l'etichetta; il modulo salva tracking e applica solo le azioni selezionate. Manifesto, reso e tracking si usano quando necessari e supportati.

## Vantaggi per il commerciante

- Meno reinserimento di ordini e indirizzi.
- Etichette e tracking collegati all'ordine.
- Valori controllati per magazzino, prodotti, dimensioni e formati.
- DHL Paket e INTERNETMARKE in un'unica interfaccia.
- Controlli espliciti per consenso, log e output.
- Guida/descrizione offline in sei lingue.

## Amministrazione e multishop

**DHL settings**, **DHL DP settings**, **Information** e **DHL Manifest** organizzano account, prodotti/carrier, etichette, servizi, resi, mittente, COD e INTERNETMARKE. Avvio rapido collega documenti locali, catalogo Silbersaiten e supporto.

Le impostazioni accettano tutti i negozi, un gruppo o un singolo negozio e l'attivazione segue il contesto. Alcuni dati restano globali/condivisi: formati/versione PPL, prodotti scaricati, dogana prodotto e tabelle senza colonna negozio. Manifest/Information richiedono un negozio. Non è quindi garantito un isolamento fisico completo per negozio.

## Privacy, servizi e compatibilità

Le richieste possono trasmettere identità, indirizzi, contenuto/valori, riferimenti, e-mail e telefono. Se il consenso è disattivato, e-mail e telefono vengono inviati a DHL per default. Si conservano account, etichette, pacchi, tracking, RMA, dogana, prodotti/prezzi, file e log opzionali; la disinstallazione non li elimina automaticamente.

Il modulo può contattare DHL Shipping/Returns/Token/Location Finder/Tracking, Deutsche Post INTERNETMARKE/Tracking, Silbersaiten per PPL, Google Maps opzionale e posta/HP ePrint. La documentazione è locale.

Il codice dichiara PrestaShop da `1.6` alla versione in esecuzione; il CHANGELOG cita PrestaShop 8 e 9. Non dichiara un intervallo PHP, pur registrando fix per 7.2 e 8.4. Il codice attuale non è compatibile con PHP 8.5 a causa della firma di `SoapClient::__doRequest()`. È necessario testare l'ambiente esatto.

## Limiti importanti

- Mittente DHL solo Germania e API 2.1.
- Account Live e prodotti contrattuali necessari.
- Nessun motore tariffe checkout, coda o pulizia automatica.
- Disponibilità, prezzi, tempi e accettazione dipendono dal vettore.
- Multishop configurabile, ma non tutti i dati sono separati per negozio.

## Vantaggi principali

- Etichette e tracking integrati in PrestaShop.
- DHL Paket e INTERNETMARKE insieme.
- Mapping prodotti e valori pratici di magazzino.
- Dogana, servizi, manifesti e resi supportati.
- Multishop documentato senza promesse eccessive.
- Sei lingue offline e accesso diretto al supporto.

## Tre testi molto brevi per la scheda

1. Crea etichette DHL Paket e INTERNETMARKE direttamente dagli ordini PrestaShop.
2. Collega i corrieri PrestaShop a DHL/Deutsche Post, tracking, dogana e resi.
3. Gestisci le spedizioni DHL e Deutsche Post contrattuali dal Back Office PrestaShop.
