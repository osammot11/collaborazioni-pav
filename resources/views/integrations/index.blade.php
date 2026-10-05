@extends('layouts.app')
@section('title', 'ChatGPT e integrazioni')
@section('content')
<section class="page-heading"><div><p class="eyebrow">Il tuo assistente, nella pipeline</p><h1>ChatGPT e integrazioni</h1><p class="muted">Collegamenti OAuth revocabili e registro delle modifiche effettuate dagli strumenti.</p></div></section>
<section class="card integration-card integration-card-wide">
    <h2>Endpoint MCP</h2>
    <code class="endpoint-url">{{ config('integrations.resource') }}</code>
    <p class="muted">In ChatGPT aggiungi un’app MCP con autenticazione OAuth. Al collegamento accedi come amministratore e controlla i permessi richiesti. Le credenziali non vanno incollate in chat.</p>
    <h2>Collegamenti</h2>
    @forelse ($connections as $clientId => $tokens)
        @php($active = $tokens->filter(fn ($token) => ! $token->revoked && $token->expires_at?->isFuture()))
        <div class="connection-row">
            <div><strong>{{ $tokens->first()->client?->name ?? 'Applicazione' }}</strong><p class="muted">{{ $active->count() }} token attivi · {{ $tokens->first()->created_at->format('d/m/Y H:i') }}</p></div>
            <form method="POST" action="{{ route('integrations.revoke', $clientId) }}" data-confirm="Revocare tutti i token di questo collegamento?">
                @csrf @method('DELETE')
                <button type="submit" class="button button-ghost">Revoca</button>
            </form>
        </div>
    @empty
        <p class="muted">Nessun collegamento autorizzato.</p>
    @endforelse
</section>
<section class="card integration-card integration-card-wide">
    <h2>Registro modifiche ChatGPT</h2>
    @forelse ($operations as $operation)
        <details class="audit-entry">
            <summary>{{ \Carbon\Carbon::parse($operation->created_at)->format('d/m/Y H:i') }} · {{ match ($operation->action) { 'create' => 'Creazione', 'delete' => 'Eliminazione', default => 'Modifica' } }} · Opportunità #{{ $operation->collaboration_id }}</summary>
            <p class="muted">Richiesta {{ $operation->request_id }} · Client {{ $operation->client_id }}</p>
            <div class="audit-grid"><div><h3>Prima</h3><pre>{{ $operation->before ? json_encode(json_decode($operation->before), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'Nuova opportunità' }}</pre></div><div><h3>Dopo</h3><pre>{{ json_encode(json_decode($operation->after), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div></div>
        </details>
    @empty
        <p class="muted">Le modifiche via MCP compariranno qui. Il registro contiene dati delle opportunità: proteggi e conserva i backup del database.</p>
    @endforelse
    {{ $operations->links() }}
</section>
@endsection
