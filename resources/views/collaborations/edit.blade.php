@extends('layouts.app')

@section('title', 'Modifica '.$collaboration->name)

@section('content')
    <div class="form-page-heading">
        <a href="{{ route('dashboard') }}" class="back-link">← Torna alla dashboard</a>
        <p class="eyebrow">Modifica collaborazione</p>
        <h1>{{ $collaboration->name }}</h1>
        <p>Aggiorna rendite, situazione, deadline e dettagli.</p>
    </div>

    <form method="POST" action="{{ route('collaborations.update', $collaboration) }}" class="form-card">
        @csrf
        @method('PUT')
        @include('collaborations._form', ['submitLabel' => 'Salva modifiche'])
    </form>
    <section class="form-card pipeline-history" aria-label="Storico pipeline">
        @php($history = $collaboration->pipelineEvents()->paginate(20, ['*'], 'history_page'))
        <p class="eyebrow">Cronologia</p>
        <h2>Storico della trattativa</h2>
        @forelse ($history as $event)
            <article class="history-event">
                <time>{{ $event->created_at->format('d/m/Y H:i') }}</time>
                <strong>{{ $event->from_stage?->label() ?? 'Inizio' }} → {{ $event->to_stage->label() }}</strong>
                <p>{{ $event->from_outcome?->label() ?? '—' }} → {{ $event->to_outcome->label() }}</p>
                @if ($event->note)<p class="muted">{{ $event->note }}</p>@endif
            </article>
        @empty
            <p class="muted">Nessun passaggio registrato.</p>
        @endforelse
        {{ $history->links('components.pagination') }}
    </section>
@endsection
