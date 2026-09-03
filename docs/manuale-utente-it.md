# DHL Deutschepost 3.2.9 — Manuale utente

## Inizia da qui

DHL Deutschepost collega gli ordini PrestaShop alle spedizioni DHL Paket per clienti commerciali e a Deutsche Post INTERNETMARKE. Dal Back Office crea etichette, salva i codici di tracciamento, gestisce la generazione massiva, i manifesti e i resi supportati e può agevolare gli indirizzi Packstation/Postfiliale nel checkout.

Il modulo non stipula un contratto DHL, non attiva prodotti, non calcola tariffe in tempo reale nel checkout e non determina i servizi inclusi nel contratto. Prima di usare Live, ottieni da DHL i dati seguenti.

## Dati necessari prima della configurazione

| Dato | Dove si ottiene | Utilizzo |
| --- | --- | --- |
| Contratto commerciale DHL | Referente commerciale DHL | Spedizioni DHL Paket reali |
| Nome utente e password GKP | Post & DHL Business Customer Portal | Autenticazione Live |
| EKP | Dati contratto GKP o documenti DHL | Identificazione dell'account |
| Numeri di fatturazione (*Abrechnungsnummern*) | Posizioni contrattuali GKP | Prodotto/procedura e partecipazione |
| Abilitazione DHL Retoure | Contratto DHL | Etichette di reso, se necessarie |
| Account Portokasse | Deutsche Post | INTERNETMARKE, se utilizzata |

Se manca un dato, richiedilo al referente del contratto. Il modulo non può ricavare né attivare numeri contrattuali.

## Primo accesso al portale DHL

1. Apri il **Post & DHL Business Customer Portal (GKP)**: <https://geschaeftskunden.dhl.de/>.
2. Usa l'utente personale ricevuto con il contratto e la password iniziale o il link di ripristino.
3. Completa l'attivazione o il cambio password richiesto.
4. Se i dati non sono arrivati, usa **Forgot password/Passwort vergessen** o contatta l'amministratore dell'account o DHL. L'accesso iniziale viene normalmente inviato dopo la registrazione commerciale.
5. Per un'API di produzione, DHL raccomanda un **utente di sistema commerciale** dedicato. Crealo o richiedilo dopo che un amministratore personale può accedere a GKP.

L'utente di sistema serve per l'API e non può accedere all'interfaccia web GKP. Mantieni almeno un amministratore personale. Non inserire client ID o client secret del DHL Developer Portal: il modulo non ha tali campi e usa le credenziali GKP configurate per Live.

## Trovare EKP, prodotto e partecipazione

In GKP apri l'area del contratto, normalmente **Vertragsdaten > Vertragspositionen**, e individua l'**Abrechnungsnummer** di ogni prodotto. I nomi del portale possono cambiare; conta il numero assegnato alla posizione contrattuale.

Il numero di fatturazione DHL Paket ha 14 caratteri:

`1234567890 01 01`

| Parte | Lunghezza | Esempio | Campo del modulo |
| --- | --- | --- | --- |
| EKP | 10 caratteri | `1234567890` | **EKP** |
| Procedura/prodotto | 2 caratteri | `01` | Seleziona il **prodotto DHL** corrispondente |
| Partecipazione | 2 caratteri | `01` | **Participation** accanto al prodotto |

Non incollare l'intero numero nel campo Participation e non inserire lì il codice prodotto centrale.

| Procedura | Prodotto nel modulo |
| --- | --- |
| `01` | DHL Paket |
| `53` | DHL Paket International |
| `54` | DHL Europaket |
| `62` | DHL Kleinpaket |
| `66` | Warenpost International |

Aggiungi solo prodotti presenti nel contratto. Uno stesso prodotto può avere più partecipazioni. DHL può assegnare valori numerici o alfanumerici, ma il campo separato **Return participation** di questa versione accetta esattamente due cifre.

Per i resi usa la partecipazione della relativa posizione DHL Retoure. `01` è comune, non garantito. La vecchia documentazione descriveva credenziali Retoure Portal e portal ID separati; la versione attuale non ha questi campi e non usa quel login.

## Requisiti e compatibilità

- PrestaShop: il modulo dichiara 1.6 come minimo e la versione in esecuzione come massimo. Il CHANGELOG include modifiche per PrestaShop 8 e 9.
- PHP: non è dichiarato un intervallo formale. Sono documentate correzioni per PHP 7.2 e 8.4. Il codice attuale non è compatibile con PHP 8.5 a causa della firma `SoapClient::__doRequest()`; usa una versione compatibile come PHP 8.4 fino all'adeguamento.
- Ambiente: usa HTTPS in uscita, cURL, JSON e mbstring; `logs`, `pdfs` e `data` devono essere scrivibili.
- DHL: il paese mittente è attualmente solo Germania (`DE`) e l'API di spedizione è fissata a `2.1`.
- Account: Live richiede credenziali GKP e prodotti contrattuali; INTERNETMARKE richiede Portokasse.

Esegui backup, prova in staging, verifica HTTPS e decidi la corrispondenza dei corrieri PrestaShop. In multishop seleziona il contesto corretto prima del salvataggio.

## Installazione e aggiornamento

### Nuova installazione

1. In **Moduli > Gestione moduli**, scegli il caricamento di un modulo e seleziona lo ZIP di release.
2. Installa **DHL Deutschepost** e apri la configurazione.
3. Segui la prima connessione descritta sotto.

### Aggiornamento senza perdere dati

Carica il nuovo ZIP o distribuisci l'intera directory `dhldp` su quella esistente. Non disinstallare prima. Esegui l'upgrade quando PrestaShop lo propone. Il percorso normale conserva chiavi e dati.

Svuota cache PrestaShop e browser solo se restano visibili template, traduzioni o risorse vecchie.

## Prima connessione a DHL

1. Apri **DHL settings**.
2. Seleziona **Sandbox** per imparare senza contratto reale; il modulo usa credenziali sandbox integrate. Usa **Live** solo in produzione.
3. In Live inserisci utente GKP/API, password ed EKP di dieci caratteri. Il salvataggio verifica l'account. Gli asterischi successivi indicano solo una password memorizzata.
4. In **DHL Products** aggiungi ogni prodotto: i due caratteri centrali dell'Abrechnungsnummer identificano il prodotto e gli ultimi due sono la Participation.
5. In **Carriers** associa ogni corriere PrestaShop alla combinazione corretta. Le azioni DHL compaiono solo per ordini con corriere associato.
6. Inserisci il mittente separando via e numero civico, oppure copia esattamente un riferimento mittente GKP.
7. Scegli il formato etichetta della stampante. Nel primo test lascia disattivati stato automatico, accettazione avvisi, resi immediati e servizi extra.
8. Salva e crea un'etichetta per un ordine di prova.

Sandbox verifica il flusso del modulo, non il contratto Live. Esegui anche una prova Live controllata.

## Creare la prima etichetta DHL

1. Apri un ordine di prova con corriere associato a DHL.
2. Nell'area DHL scegli **Generate label**.
3. Controlla via e civico del destinatario, CAP, paese, peso e dimensioni. Prima di riprovare usa **Update delivery address**.
4. Per esportazioni, verifica descrizione, valore, origine e codice tariffario di ogni posizione.
5. Seleziona solo servizi supportati da prodotto e contratto e invia.
6. Apri il PDF e controlla mittente, destinatario, prodotto, formato e numero spedizione.
7. Verifica il tracking e che stato o e-mail siano stati eseguiti una sola volta.

Separa sempre il civico dalla via. Una riga combinata è una causa frequente di rifiuto.

## Etichette massive

1. Nell'elenco ordini seleziona quelli con associazione DHL valida.
2. Esegui l'azione massiva **Generate DHL labels**.
3. Esamina i singoli errori: indirizzo, peso o servizio non valido possono riguardare un solo ordine.
4. Usa **Print last labels** per recuperare l'ultimo lotto.

Inizia con pochi ordini. Il processo massivo richiede comunque indirizzi, pesi e dati doganali corretti.

## Problemi comuni della prima etichetta

| Sintomo | Causa probabile | Controllo |
| --- | --- | --- |
| Account rifiutato al salvataggio | Utente, password o EKP Live errati; account bloccato | Verifica GKP personale e utente API separato, ripristina la password e confronta EKP col contratto. |
| Il portale funziona, il modulo no | Confusione fra utente personale e di sistema, o password scaduta | Usa le credenziali API; l'utente di sistema non accede al web GKP. |
| Nessuna azione DHL | Corriere non associato nel negozio | Salva l'associazione nel contesto dell'ordine. |
| Prodotto/partecipazione rifiutati | Parte sbagliata del numero | Nei 14 caratteri: posizioni 11–12 = prodotto, 13–14 = partecipazione. |
| Indirizzo rifiutato | Civico nella via, CAP o paese errato | Separa via/civico e verifica il formato del paese. |
| Peso/dimensioni rifiutati | Vuoto, zero, conversione errata o limite | Controlla pesi e imballo; `1` per kg o `0.001` per grammi. |
| Esportazione fallita | Mancano descrizione, valore, origine o tariffa | Completa ogni posizione; il modulo convalida 6, 8 o 10 cifre. |
| Servizio non disponibile | Prodotto, destinazione o contratto non lo supportano | Rimuovilo o chiedi conferma a DHL. |
| PDF assente | `pdfs` non scrivibile o errore API | Controlla permessi/spazio e abilita temporaneamente il log mascherato. |

## Impostazioni da decidere consapevolmente

- **Peso:** calcolalo dai prodotti solo se aggiornato; `1` per kg o `0.001` per grammi, più l'imballo realistico.
- **Stampante:** scegli il formato corretto; `100x70mm` è solo per DHL Kleinpaket e Warenpost International.
- **Stato/e-mail:** inizia senza automazioni e poi escludi messaggi doppi.
- **Avvisi:** non accettarli automaticamente nei primi test.
- **Servizi extra:** routing, GoGreen, GoGreen Plus, età, Premium e resi dipendono dal contratto e possono costare.
- **Privacy:** senza conferma, il modulo invia a DHL e-mail e telefono per impostazione predefinita. Definisci base giuridica e informativa.
- **Packstation/Postfiliale:** funzionano senza mappa; Google Maps è opzionale e richiede una chiave limitata.
- **Resi:** abilita la gestione estesa insieme ai resi PrestaShop dopo aver testato i paesi. L'invio immediato è disattivato di default.
- **Log:** solo per diagnosi; log e PDF non vengono eliminati automaticamente.

## Prima configurazione INTERNETMARKE

1. Registrati o accedi a **Portokasse**: <https://portokasse.deutschepost.de/portokasse/>. Una nuova registrazione può richiedere un codice inviato per posta.
2. Apri **DHL DP settings** e inserisci utente e password Portokasse/INTERNETMARKE.
3. Al primo collegamento, Portokasse può chiedere l'autorizzazione permanente dell'applicazione commerciale. Controlla **Meine Daten > Geschäftsanwendungen**.
4. Recupera i formati pagina e aggiorna la lista PPL se necessario.
5. Associa solo i corrieri Deutsche Post, scegli prodotto, `pdf` o `png` e mittente.
6. Per fogli PDF imposta pagina, riga e colonna e stampa una prova.

Se l'accesso fallisce, prova Portokasse direttamente e ripristina lì la password. Un prodotto visibile non garantisce né l'idoneità dell'account né il prezzo corrente.

## Multishop

Le impostazioni supportano tutti i negozi, gruppo e singolo negozio con l'ereditarietà PrestaShop. Salva valori generali globalmente e credenziali, mittenti e associazioni diverse nel singolo negozio.

Le impostazioni operative sono di norma lette per il negozio dell'ordine. Le sei tabelle però non hanno `id_shop` diretto; dogana prodotto e lista Deutsche Post sono condivise, versione PPL e formati sono globali. Manifest e Information richiedono un singolo negozio. Prova un'etichetta in ogni negozio.

## Dati, privacy, servizi e limiti

Credenziali e opzioni sono nella configurazione PrestaShop; etichette, colli, consensi, dogana e INTERNETMARKE in sei tabelle `dhldp_*`; file in `pdfs`; log in `logs`; prodotti Deutsche Post in `data/ppl.csv`.

In base al servizio, DHL o Deutsche Post possono ricevere nomi, indirizzi, contenuti, valori, riferimenti, e-mail e telefono. Integrazioni opzionali: DHL Location Finder, Google Maps, `prestamodule.silberserver.de` per PPL, posta del negozio e HP ePrint. La documentazione locale non richiede risorse esterne.

Il paese mittente DHL è limitato alla Germania, l'API alla 2.1 e non esistono tariffe checkout né worker. `cron_track.php` aggiorna il tracking con la chiave segreta: proteggi l'URL. Il vecchio mittente austriaco, il login Retoure Portal separato e il vecchio URL manuale di tracking non valgono per questa versione.

La disinstallazione rimuove schede e hook, ma conserva tabelle, configurazione, etichette, log e lista prodotti. Rimuovili manualmente dopo un backup solo se desideri la cancellazione completa.

## Checklist prima della produzione

- L'account Live è convalidato con utente API ed EKP corretti.
- Ogni corriere punta a prodotto e partecipazione del contratto.
- Mittente, via/civico, conversione peso e formato stampa sono verificati.
- Etichetta nazionale, esportazione necessaria e resi sono stati provati separatamente.
- Stati, e-mail e consenso corrispondono alla politica del negozio.
- Ogni contesto multishop ha una prova propria.
- Il log diagnostico è disattivato.

