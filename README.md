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

La suite forza SQLite `:memory:` e ignora le cache normali di configurazione/rotte. `Tests\TestCase` interrompe l’avvio se l’ambiente non è `testing` o il database non è in memoria, prima che `RefreshDatabase` possa eseguire migrazioni distruttive. Non rimuovere questa protezione e non usare il database locale/VPS per eseguire i test.

## Pipeline commerciale

La pipeline usa **un solo stato**, senza esiti separati: Da contattare, Outbound inviato, Risposta ricevuta, Demo programmata, Demo svolta, Preventivo inviato, Negoziazione, Contratto, Pagato, Rimandato e Rifiutato. Da classificare resta disponibile per le vecchie schede. Rimandato indica progetti sospesi (con eventuale follow-up), Rifiutato quelli abortiti. Nessuno dei due elimina dati. Riaprendo una scheda sospesa/rifiutata, la situazione passa a Forse salvo una diversa scelta esplicita.

**Contratto** distingue l’accordo acquisito dall’incasso. Con un piano, **Parzialmente pagato** è automatico finché resta un residuo; **Pagato** si raggiunge solo al saldo e `paid_at` è calcolata dalla data dell’ultimo incasso. Le schede storiche senza piano conservano la data manuale e non ricevono pagamenti inventati.

### Acconti, rate e rinnovi

Nel form: `payment_total` indica il totale da saldare e `payment_scope` l’accordo/periodo a cui si riferisce. `payment_schedule` contiene rate previste (`amount`, `due_on`, `note`); `payment_entries` gli incassi effettivi (`amount`, `paid_on`, `note`). Le due liste sono distinte e non si sommano tra loro. La somma incassata e la somma pianificata, separatamente, non possono superare il totale. Sono accettati solo incassi positivi e non futuri, fino a 200 righe per lista. Calcoli del saldo in centesimi interi. Il riepilogo mostra concordato/incassato/residuo e avanzamento; la prossima rata insoluta copre per primi gli importi pianificati più vecchi.

La data `payment_deadline` resta il fallback per il saldo non pianificato; il filtro delle scadenze usa la prima rata non coperta (`next_payment_due_on`, calcolata dal server). I filtri pipeline **Rate / saldo** e **Rinnovi** sono separati: aver saldato un accordo non salda un rinnovo futuro. Rimandato/Rifiutato conservano tutti gli incassi senza essere riaperti automaticamente.

`recurring_amount` è l’importo reale di ciascun rinnovo, `recurring_frequency` la frequenza (`mensile`, `trimestrale`, `semestrale`, `annuale`, `biennale`) e `next_renewal_date` la prossima scadenza. Nessuna divisione per 12: dashboard e card mostrano totali distinti per frequenza. I rinnovi **non generano automaticamente nuove rate, incassi o avanzamenti di data**. Il totale del piano resta riferito all’accordo/periodo inserito; per estenderlo ad altri periodi aggiornare il totale e l’ambito mantenendo gli incassi che vi rientrano.

La migrazione del 03/11/2026 copia le vecchie rendite mensili in rinnovi mensili senza alterarne gli importi. I rinnovi annuali precedentemente divisi per 12 vanno corretti manualmente indicando importo reale e frequenza annuale. Nessun totale concordato o acconto viene dedotto dai dati storici. `monthly_revenue` resta per compatibilità e contiene solo il rinnovo realmente mensile (zero per altre frequenze).

Il registro `finance_events` conserva le versioni precedenti delle correzioni; i form finanziari richiedono la revisione corrente per non sovrascrivere altri incassi. I salvataggi restano asincroni. MCP v2.1.0 espone campi e riepilogo finanziario negli strumenti esistenti, con gli stessi scope. Omettere un elenco lo conserva; inviarlo lo sostituisce integralmente, quindi rileggere, preservare le righe e confermare eventuali rimozioni. Non inviare `monthly_revenue` insieme ai nuovi campi di ricorrenza.

Deploy: backup SQLite, aggiornamento codice, `php artisan optimize:clear`, `php artisan migrate --force`, `php artisan optimize`, aggiornamento metadati del plugin. Il rollback di questa migrazione elimina i nuovi campi e il registro: non eseguirlo senza backup.

Gli aggiornamenti delle card, i filtri e la paginazione avvengono senza ricaricare la pagina, con messaggi di errore locali e mantenimento dello scorrimento. Anche creazione e modifica della scheda completa salvano senza navigazione. Senza JavaScript, i form tradizionali continuano a funzionare. Il server renderizza i frammenti Blade; nessun framework JavaScript o API pubblica aggiuntiva.

La migrazione del 12/10/2026 converte i precedenti esiti Perso/In attesa in Rifiutato/Rimandato, annotando il cambio nello storico e incrementando la revisione per invalidare letture MCP obsolete. Le vecchie colonne degli esiti restano solo per conservazione storica/compatibilità: non sono più campi pubblici, filtri o elementi dell’interfaccia. Il rollback non cancella la riclassificazione dei dati. Nessuna nota, data o importo viene eliminato; la migrazione del 11/10 aggiunge la data nullable senza inventare incassi.

Categoria, referente, email, servizio proposto, demo, prossima azione, follow-up e nota sullo stato si gestiscono nel form. Tutti i passaggi di stato vengono registrati nello storico. Il tipo di contratto è dedotto dagli importi: solo ricorrente = abbonamento, solo una tantum = una tantum, entrambi = ibrido.

La situazione accordo/pagamento resta per il riepilogo delle rendite, non è un esito commerciale. Il follow-up segnala le date da gestire per stati non chiusi; Contratto, Pagato e Rifiutato sono esclusi. Non invia email.

Per aggiornare la VPS: backup SQLite, aggiornamento codice, `php artisan optimize:clear`, `php artisan migrate --force`, `php artisan optimize`. MCP v2.0.0 rimuove `pipeline_outcome` dai campi/snapshot, `outcome` dai filtri e `outcomes` dalle opzioni: aggiornare i metadati del plugin dopo il deploy. I permessi OAuth restano invariati.

La board mostra al massimo 30 schede per colonna; l’elenco paginato dà accesso a tutte le opportunità. I filtri per categoria, stato e follow-up valgono per entrambe le viste.

## ChatGPT via MCP (senza Auth0)

Anche **Claude web/Desktop/mobile/Cowork** può usare gli stessi strumenti tramite `/mcp/claude`, con OAuth/DCR e callback esatto `https://claude.ai/api/mcp/auth_callback`. `/mcp` resta invariato per ChatGPT. Vedi [la guida Claude per la VPS](docs/CLAUDE-VPS.md). MCP v2.2.0 aggiunge discovery e audience dedicate, senza token statici o nuovi scope; applicare la migrazione del 08/11/2026 prima di collegarsi.

Il server `/mcp` espone lettura, ricerca, creazione, aggiornamento ed eliminazione della pipeline con OAuth Passport e PKCE S256. Richiede un amministratore distinto dall’accesso tramite codice. L’area `/integrazioni` mostra collegamenti revocabili e registro modifiche; idempotenza e revisioni proteggono da duplicati e sovrascritture. L’eliminazione definitiva di un singolo contatto richiede il permesso separato `pipeline:delete`, il nome esatto, la revisione e la conferma esplicita dell’utente in chat; il registro mantiene lo snapshot precedente. Non invia email.

Per configurazione VPS, chiavi, account amministratore e collegamento in ChatGPT segui [la guida completa](docs/CHATGPT-VPS.md). La disponibilità delle azioni di scrittura dipende dal piano ChatGPT; il server non elimina le limitazioni del piano.

Per provare OAuth localmente, dopo le migrazioni esegui una sola volta `php artisan passport:keys` e `php artisan app:create-admin`. L’accesso locale è `/login`; ChatGPT richiede un endpoint remoto HTTPS, non localhost direttamente. Le chiavi non vanno committate. La suite testa OAuth con chiavi effimere in memoria e un database SQLite isolato, senza usare i dati locali o della VPS.
