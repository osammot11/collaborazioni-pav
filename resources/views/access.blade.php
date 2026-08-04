<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#635bff">
    <title>Accesso · Produce a Value</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="access-page">
    <div class="access-gradient"></div>
    <main class="access-shell">
        <section class="access-intro">
            <div class="brand brand-light">
                <span class="brand-mark">P</span>
                <span class="brand-copy"><strong>Produce a Value</strong><small>Collaboration desk</small></span>
            </div>
            <p class="eyebrow eyebrow-light">Opportunità, in ordine</p>
            <h1>Il valore delle prossime collaborazioni, tutto in un posto.</h1>
            <p>Monitora accordi, rendite e scadenze con una vista semplice e immediata.</p>
        </section>

        <section class="access-card" aria-labelledby="access-title">
            <div class="access-card-icon" aria-hidden="true">↗</div>
            <p class="eyebrow">Area riservata</p>
            <h2 id="access-title">Bentornato</h2>
            <p class="muted">Inserisci il codice per accedere alla dashboard.</p>

            @if (session('success'))
                <div class="flash flash-success" role="status">{{ session('success') }}</div>
            @endif

            <form method="POST" action="{{ route('access.authenticate') }}" class="access-form">
                @csrf
                <label for="access_code">Codice di accesso</label>
                <input
                    id="access_code"
                    name="access_code"
                    type="password"
                    inputmode="numeric"
                    autocomplete="current-password"
                    autofocus
                    class="@error('access_code') is-invalid @enderror"
                    value="{{ old('access_code') }}"
                    placeholder="••••"
                >
                @error('access_code')<p class="field-error">{{ $message }}</p>@enderror
                <button type="submit" class="button button-primary button-wide">Accedi <span aria-hidden="true">→</span></button>
            </form>
            <p class="access-footnote"><span aria-hidden="true">●</span> Connessione protetta da sessione</p>
        </section>
    </main>
</body>
</html>
