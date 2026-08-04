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
