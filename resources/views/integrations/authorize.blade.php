@extends('layouts.app')
@section('title', 'Autorizzazione ChatGPT')
@section('content')
<section class="integration-card card">
    <p class="eyebrow">Consenso OAuth</p>
    <h1>Collega {{ $client->name }}</h1>
    <p>L’applicazione richiede accesso alla pipeline Produce a Value con questi permessi:</p>
    <ul class="permission-list">
        @foreach ($scopes as $scope)<li>{{ $scope->description }}</li>@endforeach
    </ul>
    @if (collect($scopes)->contains(fn ($scope) => $scope->id === 'pipeline:delete'))
        <p class="field-error">Attenzione: stai autorizzando anche l’eliminazione definitiva di contatti e del loro storico di pipeline. Non esiste un cestino o ripristino nell’app.</p>
    @endif
    <p class="muted">Il nome dell’app è dichiarato dal richiedente. Approva solo se hai avviato tu il collegamento in ChatGPT. Puoi revocare l’accesso dalla pagina ChatGPT del gestionale.</p>
    <div class="integration-actions">
        <form method="POST" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="button button-primary">Autorizza collegamento</button>
        </form>
        <form method="POST" action="{{ route('passport.authorizations.deny') }}">
            @csrf @method('DELETE')
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="button button-ghost">Rifiuta</button>
        </form>
    </div>
</section>
@endsection
