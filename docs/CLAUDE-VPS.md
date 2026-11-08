# Collegare Claude alla pipeline Produce a Value

Stesso gestionale e stessi sei strumenti di ChatGPT, senza Auth0 o credenziali statiche. L’integrazione aggiunge OAuth/DCR per i client **hosted**: claude.ai, Claude Desktop, mobile e Cowork. Non configura un server locale in `claude_desktop_config.json`, né Claude Code con callback loopback/CIMD.

Riferimenti ufficiali, verificati l’8 novembre 2026:

- [Guida ai connettori personalizzati](https://claude.com/docs/connectors/custom/add-unlisted)
- [Requisiti OAuth, callback e registrazione automatica](https://claude.com/docs/connectors/building/authentication)
- [Costruire un server MCP per Claude](https://claude.com/docs/connectors/building)

## 1. Pubblicare il codice sulla VPS

Prima pubblicare commit e push su GitHub. Nessun comando qui effettua il push delle modifiche locali. Salvare un backup consistente dello SQLite della VPS; non copiare un database locale o di test sopra quello di produzione.

Come utente `deploy`:

```bash
cd /var/www/collaborazioni-pav
git status --short
git pull --ff-only origin main
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
```

La nuova migrazione aggiunge `oauth_resource_bindings`: conserva associazioni hash tra grant OAuth e destinazione MCP. Non conserva codici OAuth o refresh token in chiaro e non modifica le collaborazioni. Non rigenerare `APP_KEY`, chiavi Passport o account amministratore. I token ChatGPT già emessi rimangono validi sul loro endpoint.

Con `APP_URL=https://collaborazioni.tommasogiovannoni.com`, gli endpoint sono:

| Client | URL |
| --- | --- |
| ChatGPT, invariato | `https://collaborazioni.tommasogiovannoni.com/mcp` |
| Claude | `https://collaborazioni.tommasogiovannoni.com/mcp/claude` |

Il callback Claude è già abilitato per default e non dipende dal valore preesistente di `CHATGPT_REDIRECT_URIS`. Per renderlo esplicito nel `.env`:

```dotenv
CLAUDE_REDIRECT_URIS=https://claude.ai/api/mcp/auth_callback
```

Non modificare `CHATGPT_REDIRECT_URIS`, non usare wildcard e non aggiungere callback arbitrari per aggirare errori. Dopo modifiche `.env`, rigenerare la configurazione con `php artisan config:cache`.

## 2. Verificare trasporto e discovery

Claude deve raggiungere questi URL via HTTPS pubblico, non solo dal tuo computer. Controllare firewall/WAF e inoltro di `Authorization` a PHP-FPM. Gli [indirizzi ufficiali Anthropic](https://platform.claude.com/docs/en/api/ip-addresses) vanno verificati alla configurazione del firewall; non sono incorporati nel codice. Nessun CORS permissivo è necessario: le chiamate remote arrivano dal cloud di Anthropic.

```bash
curl -i https://collaborazioni.tommasogiovannoni.com/.well-known/oauth-protected-resource/mcp/claude
curl -i https://collaborazioni.tommasogiovannoni.com/.well-known/oauth-authorization-server
curl -i -X POST https://collaborazioni.tommasogiovannoni.com/mcp/claude \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  --data '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}}}'
```

Le discovery rispondono `200`. Il primo documento deve dichiarare esattamente la risorsa `/mcp/claude`. L’ultimo comando senza token deve rispondere **401**, con:

```text
WWW-Authenticate: Bearer resource_metadata="https://collaborazioni.tommasogiovannoni.com/.well-known/oauth-protected-resource/mcp/claude"
```

È previsto: avvia l’autenticazione. Anche `GET /mcp/claude` senza token restituisce 401; autenticato, risponde 405 perché non offre SSE persistente. Il trasporto è Streamable HTTP via POST. `/mcp` conserva la discovery pubblica precedente per ChatGPT.

## 3. Aggiungere il connettore in Claude

1. Aprire **Customize → Connectors → Add custom connector**. Per Team/Enterprise l’aggiunta iniziale può richiedere il titolare/amministratore dell’organizzazione, come descritto nella guida allegata.
2. Nome: **Produce a Value**. URL: **`https://collaborazioni.tommasogiovannoni.com/mcp/claude`**.
3. Se richiesto, **Authentication → Sign in now** e **OAuth client → Register automatically**. Questo server espone DCR, non CIMD: non scegliere **Use Claude’s published identity**.
4. Lasciare vuoti client ID/secret nelle interfacce che mostrano solo **Advanced settings**: con DCR vengono registrati automaticamente. Non aggiungere API key, bearer statici o il codice `1804` agli header.
5. Avviare il collegamento. Nella pagina del gestionale usare email e password dell’amministratore già esistente. La destinazione del consenso deve essere **claude.ai**.
6. Controllare i permessi richiesti e approvare solo il collegamento appena avviato. Se è presente `pipeline:delete`, si sta autorizzando anche la cancellazione definitiva: la pagina lo segnala. Senza tale scope, l’eliminazione resta bloccata. Il nome client è dichiarato dal richiedente e non certifica l’identità di Claude.
7. Attivare il connettore nella conversazione tramite il menu **+ → Connectors**, secondo l’interfaccia disponibile.

I nomi delle voci possono variare tra le due versioni della finestra di configurazione riportate nella guida ufficiale. Nessuna registrazione nel directory pubblico è necessaria per questo connettore privato.

## 4. Prove e controlli

Prima provare in sola lettura: «Mostrami le opportunità della pipeline senza modificarle». Poi, con consenso esplicito, creare una scheda chiaramente di prova e verificarla nel gestionale. I sei strumenti sono identici a ChatGPT: `pipeline_options`, `search_opportunities`, `get_opportunity`, `create_opportunity`, `update_opportunity`, `delete_opportunity`. Rate, incassi e rinnovi sono condivisi, non duplicati.

La pagina **Integrazioni** distingue i collegamenti per client e permette la revoca; il registro riporta il client di ciascuna modifica. Revocare Claude non revoca un diverso client ChatGPT. Scopes, amministratore, revisioni, idempotenza e conferma per cancellazioni restano obbligatori. Non incollare password, codici OAuth o token nelle conversazioni.

Il flusso usa PKCE S256, token endpoint form-urlencoded, refresh ruotati e vincolo della destinazione anche su code exchange/refresh. Un token ChatGPT non è utilizzabile sul percorso Claude e viceversa. Per cambiare endpoint serve un nuovo consenso, non la modifica del parametro `resource` di un token esistente.

Diagnosi rapida:

- **404 sui nuovi URL:** codice non aggiornato oppure route cache vecchia.
- **401 prima dell’accesso:** previsto; controllare il puntatore `resource_metadata`.
- **Errore callback:** verificare l’URL esatto, l’allowlist e la cache di configurazione. Non aggiungere loopback per Claude web/Desktop/Cowork.
- **invalid_target / invalid_grant:** URL/resource non coerenti, grant scaduto/revocato o token di un altro endpoint. Ricollegare con l’URL corretto.
- **403 origin_not_allowed:** verificare proxy e header; non disabilitare il controllo dell’origine.
- **Errore SQL sulla nuova tabella:** eseguire la migrazione prima di collegare Claude.
- **Timeout:** verificare latenza di discovery, registrazione e token endpoint/WAF. Il server non dipende da chiamate esterne per emettere token.

La suite locale prova OAuth completo, callback esatti, refresh, separazione delle risorse, protocolli 2025-06-18/2025-11-25 e regressioni ChatGPT. Il collegamento effettivo su claude.ai va completato dopo il deploy: i test non configurano automaticamente l’account Claude o la VPS.
