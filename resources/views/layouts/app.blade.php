<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#635bff">
    <title>@yield('title', 'Collaborazioni') · Produce a Value</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <div class="ambient ambient-one"></div>
    <div class="ambient ambient-two"></div>

    <header class="site-header">
        <a class="brand" href="{{ route('dashboard') }}" aria-label="Vai alla dashboard">
            <span class="brand-mark" aria-hidden="true">P</span>
            <span class="brand-copy">
                <strong>Produce a Value</strong>
                <small>Collaboration desk</small>
            </span>
        </a>

        <nav class="header-actions" aria-label="Azioni principali">
            <a href="{{ route('collaborations.create') }}" class="button button-primary button-compact">
                <span aria-hidden="true">＋</span> Nuova
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="button button-ghost button-compact">Esci</button>
            </form>
        </nav>
    </header>

    <main class="page-shell">
        @if (session('success'))
            <div class="flash flash-success" role="status">
                <span class="flash-icon" aria-hidden="true">✓</span>
                {{ session('success') }}
            </div>
        @endif

        @yield('content')
    </main>

    <script>
        document.querySelectorAll('[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!window.confirm(form.dataset.confirm)) event.preventDefault();
            });
        });
    </script>
</body>
</html>
