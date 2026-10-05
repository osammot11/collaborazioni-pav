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

La pagina `/pipeline` organizza i contatti in fasi: Da contattare, Outbound inviato, Risposta ricevuta, Demo programmata, Demo svolta, Preventivo inviato, Negoziazione e Contratto. Ogni opportunità mantiene un esito indipendente: In corso/interessato, In attesa, Forse, Senza risposta, Rifiutato/perso o Acquisito. Contratto imposta automaticamente Acquisito. È possibile riaprire una trattativa cambiando fase ed esito.

Categoria, referente, email, servizio proposto, tipo e data demo, prossima azione e follow-up si gestiscono nel form. Le colonne permettono di cambiare fase/esito; tutti i passaggi vengono registrati nello storico della scheda. Il tipo di contratto è dedotto dagli importi: solo mensile = abbonamento, solo una tantum = una tantum, entrambi = ibrido.

Gli esiti commerciali sono separati dalla precedente situazione accordo/pagamento. Il follow-up segnala le date da gestire nell’app; non invia email e non classifica automaticamente una mancata risposta.

Per aggiornare un’installazione esistente, eseguire un backup SQLite prima di `php artisan migrate --force`. La migrazione conserva i dati e associa Pagato/Confermato a Contratto acquisito; le altre situazioni restano Da classificare con un esito corrispondente. Lo storico annota l’importazione e le associazioni vanno verificate. Non vengono inventate date outbound o demo.

La board mostra al massimo 30 schede per colonna; l’elenco paginato sottostante permette di accedere a tutte le opportunità. I filtri per categoria, fase, esito e follow-up valgono per entrambe le viste.

## ChatGPT via MCP (senza Auth0)

Il server `/mcp` espone lettura, ricerca, creazione e aggiornamento della pipeline con OAuth Passport e PKCE S256. Richiede un amministratore distinto dall’accesso tramite codice. L’area `/integrazioni` mostra collegamenti revocabili e registro modifiche; idempotenza e revisioni proteggono da duplicati e sovrascritture. Non espone eliminazioni o invio email.

Per configurazione VPS, chiavi, account amministratore e collegamento in ChatGPT segui [la guida completa](docs/CHATGPT-VPS.md). La disponibilità delle azioni di scrittura dipende dal piano ChatGPT; il server non elimina le limitazioni del piano.

Per provare OAuth localmente, dopo le migrazioni esegui una sola volta `php artisan passport:keys` e `php artisan app:create-admin`. L’accesso locale è `/login`; ChatGPT richiede un endpoint remoto HTTPS, non localhost direttamente. Le chiavi non vanno committate. La suite testa OAuth con chiavi effimere in memoria e un database SQLite isolato, senza usare i dati locali o della VPS.
