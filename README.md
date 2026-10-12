# Collaborazioni · Produce a Value

Gestionale Laravel per monitorare collaborazioni, rendite e deadline di pagamento.

## Avvio

Il progetto richiede PHP 8.3 o superiore, Composer e SQLite.

```bash
composer setup
composer dev
```

Apri [http://localhost:8000](http://localhost:8000) e inserisci il codice di accesso configurato in `.env` (`1804` nell’installazione locale).

Se il progetto è già configurato, è sufficiente:

```bash
composer dev
```

## Configurazione

Le impostazioni principali si trovano in `.env`:

```dotenv
DB_CONNECTION=sqlite
COLLABORATION_ACCESS_CODE=1804
APP_LOCALE=it
APP_TIMEZONE=Europe/Rome
```

Il database locale è `database/database.sqlite`. L’interfaccia usa Blade e il CSS personalizzato in `public/css/app.css`; non richiede Node.js, Vite, Tailwind o altri framework frontend.

## Test e qualità

```bash
composer test
./vendor/bin/pint --test
```

La suite copre accesso, rate limit, CRUD, validazione, importi in formato italiano, dashboard, filtri, paginazione e stati delle deadline.

## Pipeline commerciale

La pipeline usa **un solo stato**, senza esiti separati: Da contattare, Outbound inviato, Risposta ricevuta, Demo programmata, Demo svolta, Preventivo inviato, Negoziazione, Contratto, Pagato, Rimandato e Rifiutato. Da classificare resta disponibile per le vecchie schede. Rimandato indica progetti sospesi (con eventuale follow-up), Rifiutato quelli abortiti. Nessuno dei due elimina dati. Riaprendo una scheda sospesa/rifiutata, la situazione passa a Forse salvo una diversa scelta esplicita.

**Contratto** distingue l’accordo acquisito dall’incasso: non segna automaticamente il pagamento. **Pagato** imposta la situazione Pagato e richiede `paid_at` (data effettiva non futura). `payment_deadline` resta la scadenza prevista. Le date sono visibili e modificabili dalle card e dal form. Per gli abbonamenti rappresentano il pagamento annotato, non uno storico delle rate.

Gli aggiornamenti delle card, i filtri e la paginazione avvengono senza ricaricare la pagina, con messaggi di errore locali e mantenimento dello scorrimento. Anche creazione e modifica della scheda completa salvano senza navigazione. Senza JavaScript, i form tradizionali continuano a funzionare. Il server renderizza i frammenti Blade; nessun framework JavaScript o API pubblica aggiuntiva.

La migrazione del 12/10/2026 converte i precedenti esiti Perso/In attesa in Rifiutato/Rimandato, annotando il cambio nello storico e incrementando la revisione per invalidare letture MCP obsolete. Le vecchie colonne degli esiti restano solo per conservazione storica/compatibilità: non sono più campi pubblici, filtri o elementi dell’interfaccia. Il rollback non cancella la riclassificazione dei dati. Nessuna nota, data o importo viene eliminato; la migrazione del 11/10 aggiunge la data nullable senza inventare incassi.

Categoria, referente, email, servizio proposto, demo, prossima azione, follow-up e nota sullo stato si gestiscono nel form. Tutti i passaggi di stato vengono registrati nello storico. Il tipo di contratto è dedotto dagli importi: solo mensile = abbonamento, solo una tantum = una tantum, entrambi = ibrido.

La situazione accordo/pagamento resta per il riepilogo delle rendite, non è un esito commerciale. Il follow-up segnala le date da gestire per stati non chiusi; Contratto, Pagato e Rifiutato sono esclusi. Non invia email.

Per aggiornare la VPS: backup SQLite, aggiornamento codice, `php artisan optimize:clear`, `php artisan migrate --force`, `php artisan optimize`. MCP v2.0.0 rimuove `pipeline_outcome` dai campi/snapshot, `outcome` dai filtri e `outcomes` dalle opzioni: aggiornare i metadati del plugin dopo il deploy. I permessi OAuth restano invariati.

La board mostra al massimo 30 schede per colonna; l’elenco paginato dà accesso a tutte le opportunità. I filtri per categoria, stato e follow-up valgono per entrambe le viste.

## ChatGPT via MCP (senza Auth0)

Il server `/mcp` espone lettura, ricerca, creazione, aggiornamento ed eliminazione della pipeline con OAuth Passport e PKCE S256. Richiede un amministratore distinto dall’accesso tramite codice. L’area `/integrazioni` mostra collegamenti revocabili e registro modifiche; idempotenza e revisioni proteggono da duplicati e sovrascritture. L’eliminazione definitiva di un singolo contatto richiede il permesso separato `pipeline:delete`, il nome esatto, la revisione e la conferma esplicita dell’utente in chat; il registro mantiene lo snapshot precedente. Non invia email.

Per configurazione VPS, chiavi, account amministratore e collegamento in ChatGPT segui [la guida completa](docs/CHATGPT-VPS.md). La disponibilità delle azioni di scrittura dipende dal piano ChatGPT; il server non elimina le limitazioni del piano.

Per provare OAuth localmente, dopo le migrazioni esegui una sola volta `php artisan passport:keys` e `php artisan app:create-admin`. L’accesso locale è `/login`; ChatGPT richiede un endpoint remoto HTTPS, non localhost direttamente. Le chiavi non vanno committate. La suite testa OAuth con chiavi effimere in memoria e un database SQLite isolato, senza usare i dati locali o della VPS.
