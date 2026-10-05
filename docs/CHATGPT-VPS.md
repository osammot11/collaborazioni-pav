# Collegare ChatGPT alla pipeline sulla VPS IONOS

Questa versione espone strumenti MCP via Streamable HTTP, con OAuth gestito da Laravel Passport. Non usa Auth0, chiavi API da incollare in chat, account registrabili pubblicamente o GPT personalizzati.

## Prima di iniziare

Il codice aggiornato deve essere stato pubblicato su GitHub prima del `git pull` sulla VPS. Questa guida non pubblica automaticamente le modifiche locali. Il dominio deve funzionare in **HTTPS**, con certificato valido e porta 443 raggiungibile. Non rigenerare `APP_KEY` su un’installazione esistente.

Verifica il piano ChatGPT e la presenza della funzione per creare app MCP. La [documentazione OpenAI](https://help.openai.com/en/articles/12584461-developer-mode-and-mcp-apps-in-chatgpt) indica attualmente scrittura MCP in beta per Business, Enterprise ed Edu; Pro dispone di lettura/fetch. Disponibilità e interfaccia possono cambiare. Non è sufficiente pubblicare il server per abilitare capacità assenti dal proprio piano.

## 1. Aggiornamento sicuro dell’app

Accedi alla VPS con l’utente `deploy`. Prima controlla `git status`: se ci sono modifiche locali, preservale e risolvi gli eventuali conflitti; non usare reset distruttivi.

```bash
cd /var/www/collaborazioni-pav
git status
php artisan down
```

Crea un backup consistente con SQLite, non una semplice copia del file mentre è in uso. Se il tuo `DB_DATABASE` punta altrove, adatta il percorso. La directory backup non deve essere sotto `public/`.

```bash
mkdir -p /var/www/collaborazioni-pav/storage/app/backups
chmod 700 /var/www/collaborazioni-pav/storage/app/backups
sqlite3 /var/www/collaborazioni-pav/database/database.sqlite ".backup '/var/www/collaborazioni-pav/storage/app/backups/pre-mcp-$(date +%Y%m%d-%H%M%S).sqlite'"
git pull --ff-only origin main
composer install --no-dev --prefer-dist --optimize-autoloader
```

Conserva una copia privata di `.env`, `APP_KEY` e delle eventuali chiavi Passport già esistenti. Non caricare backup o credenziali su GitHub. Se un comando fallisce, correggi l’errore prima di riaprire il sito.

## 2. Configurazione ambiente

Modifica `.env` con `nano .env`, mantenendo la chiave e il database esistenti:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://collaborazioni.tommasogiovannoni.com
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/collaborazioni-pav/database/database.sqlite
SESSION_SECURE_COOKIE=true
CHATGPT_REDIRECT_URIS=https://chatgpt.com/connector_platform_oauth_redirect
```

`APP_URL` è l’issuer OAuth e definisce anche la risorsa MCP: deve coincidere esattamente con il dominio pubblico HTTPS, senza sottocartella o slash finale. L’app aggiunge `iss` ai callback OAuth, consentendo il callback stabile ChatGPT. Se il pannello ChatGPT ti mostra un callback diverso, copia **quell’URL esatto** in `CHATGPT_REDIRECT_URIS`. Più URL possono essere separati da virgola; niente wildcard. Dopo ogni modifica a `.env`, ricrea la cache della configurazione.

L’accesso tramite codice continua a funzionare. Per un sito esposto a Internet è consigliabile cambiare il codice `1804` con uno più lungo: questo codice non concede accesso OAuth, ma consente comunque di usare il gestionale.

```bash
php artisan optimize:clear
php artisan migrate --force
```

La migrazione conserva le collaborazioni esistenti, aggiunge pipeline, revisioni, registro e tabelle OAuth. Verifica le opportunità legacy nella pipeline: Pagato/Confermato sono importate come Contratto/Acquisito; le altre restano Da classificare. Questo è un punto di partenza, non una ricostruzione automatica delle tue trattative.

## 3. Chiavi Passport e amministratore

**Solo alla prima configurazione**, se non esistono già entrambe le chiavi:

```bash
php artisan passport:keys
```

Non usare `--force` durante aggiornamenti normali: cambiare la coppia di chiavi invalida i token in uso. Le chiavi sono ignorate da Git e devono rimanere sulla VPS. PHP-FPM deve poterle leggere: il comando genera la privata con permessi `600`, ma di solito FPM usa `www-data`. Con configurazione standard Ubuntu:

```bash
sudo chown deploy:www-data storage/oauth-private.key storage/oauth-public.key
chmod 640 storage/oauth-private.key storage/oauth-public.key
php artisan app:create-admin
```

Il comando chiede nome, email e password (almeno 12 caratteri), con conferma nascosta. Non usa password predefinite e non stampa credenziali. Scegli una password diversa dal codice dashboard. Se hai già creato l’amministratore non ripetere il comando con la stessa email. Non sono previsti registrazione pubblica o recupero password via email.

Verifica che `www-data` possa scrivere in `storage/`, `bootstrap/cache/` e nella directory e nel file del database SQLite, come nel deployment iniziale. Non usare permessi `777`.

```bash
php artisan optimize
php artisan up
```

## 4. Verifiche endpoint

Nginx deve indirizzare la radice a `/var/www/collaborazioni-pav/public` e inoltrare le richieste Laravel con `try_files $uri $uri/ /index.php?$query_string`. Non bloccare `/.well-known/`: alcune regole generiche per i file nascosti lo bloccano. La location PHP deve inoltrare l’header `Authorization` a PHP-FPM; nel blocco FastCGI, se non già presente:

```nginx
fastcgi_param HTTP_AUTHORIZATION $http_authorization;
```

Dopo eventuali modifiche Nginx: `sudo nginx -t` e, solo se il test passa, `sudo systemctl reload nginx`.

```bash
curl -i https://collaborazioni.tommasogiovannoni.com/.well-known/oauth-protected-resource/mcp
curl -i https://collaborazioni.tommasogiovannoni.com/.well-known/oauth-authorization-server
curl -i -X POST https://collaborazioni.tommasogiovannoni.com/mcp \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  --data '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

I primi due rispondono `200` con metadata JSON. L’ultimo restituisce i sei strumenti e i permessi richiesti, senza dati dei clienti. La chiamata seguente **deve** rispondere `401` con `WWW-Authenticate` e l’URL dei metadata:

```bash
curl -i -X POST https://collaborazioni.tommasogiovannoni.com/mcp \
  -H 'Content-Type: application/json' \
  --data '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"search_opportunities","arguments":{}}}'
```

`GET /mcp` restituisce intenzionalmente `405`: il trasporto usa POST e non richiede un flusso SSE persistente. Non testarlo con `curl -I` aspettandoti una pagina HTML.

## 5. Creazione dell’app in ChatGPT

Sul web, abilita Developer mode se disponibile e consentito dal tuo workspace. In Impostazioni → App, oppure Impostazioni workspace → App, scegli **Crea app**. I nomi delle voci variano per piano e aggiornamenti.

- Nome: `Produce a Value`.
- URL MCP: `https://collaborazioni.tommasogiovannoni.com/mcp`.
- Autenticazione: **OAuth**, non “nessuna autenticazione”.
- Registrazione client: dinamica/DCR, se il pannello propone la scelta. Il server pubblica `/oauth/register`; non richiede client secret.
- Scope, se il pannello li chiede: `pipeline:read pipeline:write offline_access`. Per una connessione in sola lettura usa `pipeline:read offline_access`.

Avvia la scansione strumenti. Segui il redirect al gestionale, accedi con email/password dell’amministratore e controlla i permessi prima di autorizzare. Il codice `1804` non sostituisce questo login. Se appare un errore di callback, controlla l’URL esatto richiesto e aggiorna l’allowlist `.env`, poi `php artisan config:cache`.

Nella normale chat seleziona/menziona l’app. Non serve creare un GPT personalizzato. Non inviare password, token o chiavi private nella conversazione.

## 6. Prove pratiche e revoca

Prova prima la lettura: “Mostrami le opportunità in negoziazione” oppure “Quali follow-up sono scaduti?”. Poi crea una opportunità di prova chiedendo esplicitamente: “Aggiungi Test ChatGPT, categoria Hotel, fase Da contattare, nessun importo”. Verifica il risultato nella dashboard prima di operare su dati reali.

Le funzioni disponibili sono:

| Strumento | Permesso | Uso |
| --- | --- | --- |
| `pipeline_options` | lettura | Valori e regole ammessi |
| `search_opportunities` | lettura | Ricerca paginata, filtri, follow-up |
| `get_opportunity` | lettura | Scheda, revisione e storico |
| `create_opportunity` | scrittura | Nuova opportunità |
| `update_opportunity` | scrittura | Modifica parziale, note e follow-up |
| `delete_opportunity` | eliminazione (`pipeline:delete`) | Elimina definitivamente un singolo contatto dopo conferma |

Gli importi MCP sono stringhe come `1250.00`, le date `YYYY-MM-DD`. La UI continua a mostrare importi italiani. I campi omessi in una modifica sono preservati; `null` cancella i campi opzionali. Il passaggio a Contratto imposta Acquisito, ma non segna automaticamente i pagamenti come incassati.

Ogni modifica richiede un UUID `request_id`. Ritentare la stessa richiesta con lo stesso UUID non crea duplicati; riutilizzarlo con dati diversi produce errore. Gli aggiornamenti richiedono `expected_revision` dalla lettura più recente: su conflitto, ChatGPT deve rileggere e rivalutare la modifica, non sovrascrivere alla cieca.

Apri `/integrazioni` con l’amministratore per leggere il registro prima/dopo e **Revoca** il collegamento per bloccare sia access token sia refresh token. Il registro mantiene le modifiche anche dopo revoca/eliminazione dell’opportunità e contiene dati riservati: includilo nella protezione del database e nella politica di conservazione. Eliminare l’app soltanto da ChatGPT non sostituisce una revoca dal gestionale.

L’eliminazione è definitiva: rimuove il contatto/opportunità e lo storico della pipeline, ma conserva lo snapshot precedente nel registro integrazioni. Non esiste un cestino o un pulsante di ripristino. `delete_opportunity` richiede `id`, `expected_revision`, `confirm_name` esattamente uguale al nome corrente e `request_id` UUID. Prima di chiamarlo l’assistente deve rileggere la scheda, mostrare nome e ID e ottenere la conferma esplicita dell’utente. Il nome verificato protegge da errori di selezione, ma non è una prova tecnica del consenso umano: le conferme del client e il permesso OAuth restano necessari. Non supporta eliminazioni massive.

### Abilitare l’eliminazione su un plugin già collegato

Dopo aver pubblicato il codice aggiornato su GitHub, sulla VPS esegui `git pull --ff-only origin main`, `php artisan optimize:clear` e `php artisan optimize`. Non servono nuove migrazioni né rigenerare chiavi. Nel plugin ChatGPT aggiorna/scansiona nuovamente gli strumenti e abilita `delete_opportunity` dove richiesto dai controlli del workspace. Riautorizza il collegamento includendo `pipeline:delete`: i token precedenti con solo lettura/scrittura non possono eliminare. Se i nuovi scope non vengono richiesti, revoca il vecchio collegamento da `/integrazioni` e riconnetti il plugin con `pipeline:read pipeline:write pipeline:delete offline_access`.

Non sono disponibili strumenti di invio email. ChatGPT non legge automaticamente la tua casella: outbound e risposte vanno registrati nell’app o descritti nella conversazione. Non ci sono esecuzioni autonome/schedulate.

## Sicurezza e manutenzione

- Access token: un’ora; refresh token: 30 giorni, ruotati al rinnovo da Passport. Login e registrazione client hanno rate limit.
- PKCE obbligatorio S256, callback esatti, consenso amministratore e CSRF sulle pagine web.
- Passport verifica firma JWT, scadenza e revoca; il middleware verifica anche il binding del token alla risorsa MCP nel database. Il JWT standard Passport usa il client come audience; il vincolo alla risorsa viene quindi imposto separatamente, non affidandosi soltanto a quel claim.
- Il contenuto di note/descrizioni è trattato come dato, non istruzioni per l’assistente. Questo riduce il rischio di prompt injection, ma non garantisce che ChatGPT non commetta errori: controlla le conferme e il registro.
- Il server non impone un secondo consenso umano per ogni chiamata: i permessi OAuth e le conferme del client controllano le modifiche. Per escluderle completamente, collega con il solo scope `pipeline:read`.
- Nessun servizio esterno di autenticazione o costo Auth0. Il gestionale non chiama le API OpenAI; gli eventuali costi del piano ChatGPT sono separati.
- Non rigenerare `APP_KEY` o le chiavi Passport durante normali aggiornamenti. Proteggi backup, `.env` e chiave privata.

Per diagnosi controlla `storage/logs/laravel.log` localmente, senza condividere token, cookie, authorization code o credenziali. `401` indica token assente/non valido/non associato alla risorsa; un risultato MCP `isError` può indicare permessi insufficienti, validazione o conflitto. `422` alla registrazione indica spesso un callback non consentito.

Fonti ufficiali: [OAuth per app OpenAI](https://developers.openai.com/plugins/build/auth), [Laravel MCP](https://laravel.com/docs/13.x/mcp), [Laravel Passport](https://laravel.com/docs/13.x/passport).
