@extends('layouts.app')
@section('title', 'Pipeline commerciale')
@section('content')
    <section class="hero-row pipeline-hero">
        <div>
            <p class="eyebrow">Sviluppo commerciale</p>
            <h1>Dall’outbound al contratto</h1>
            <p class="hero-subtitle">Tieni il filo di ogni opportunità, della prossima azione e dell’esito.</p>
        </div>
        <a class="button button-primary" href="{{ route('collaborations.create') }}">＋ Nuovo contatto</a>
    </section>
    <a class="follow-up-banner" href="{{ route('pipeline', ['follow_up' => 'due']) }}">{{ $dueCount }} follow-up da gestire oggi o scaduti →</a>
    @if ($errors->any())
        <div class="flash flash-error" role="alert"><div>@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>
    @endif
    <section class="list-card pipeline-filters">
        <form method="GET" action="{{ route('pipeline') }}" class="filters">
            <div class="search-field"><label class="sr-only" for="pipeline_search">Cerca opportunità</label><input id="pipeline_search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nome, referente, email, servizio…"></div>
            <label class="sr-only" for="category">Categoria</label>
            <select id="category" name="category"><option value="">Tutte le categorie</option>@foreach ($categories as $category)<option @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>@endforeach</select>
            <label class="sr-only" for="stage">Fase</label>
            <select id="stage" name="stage"><option value="">Tutte le fasi</option>@foreach ($stages as $stage)<option value="{{ $stage->value }}" @selected(($filters['stage'] ?? '') === $stage->value)>{{ $stage->label() }}</option>@endforeach</select>
            <label class="sr-only" for="outcome">Esito</label>
            <select id="outcome" name="outcome"><option value="">Tutti gli esiti</option>@foreach ($outcomes as $outcome)<option value="{{ $outcome->value }}" @selected(($filters['outcome'] ?? '') === $outcome->value)>{{ $outcome->label() }}</option>@endforeach</select>
            <label class="sr-only" for="follow_up">Follow-up</label>
            <select id="follow_up" name="follow_up"><option value="">Tutti i follow-up</option>@foreach (['due' => 'Oggi / scaduti', 'upcoming' => 'Prossimi', 'none' => 'Non pianificati'] as $value => $label)<option value="{{ $value }}" @selected(($filters['follow_up'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
            <button class="button button-secondary">Filtra</button><a class="button button-link" href="{{ route('pipeline') }}">Azzera</a>
        </form>
    </section>
    <p class="muted pipeline-help">Puoi saltare fasi o riaprire una trattativa. Gli esiti restano nella fase raggiunta: “Senza risposta” è una tua valutazione, non viene assegnata automaticamente.</p>
    <div class="pipeline-board" role="region" aria-label="Pipeline per fase" tabindex="0">
        @foreach ($columns as $column)
            @if ($column['stage']->value !== 'da_classificare' || $column['total'] > 0)
                <section class="pipeline-column">
                    <div class="pipeline-column-heading"><h2>{{ $column['stage']->label() }}</h2><span class="status-count">{{ $column['total'] }}</span></div>
                    @forelse ($column['items'] as $item)
                        <article class="opportunity-card">
                            <span class="outcome-pill outcome-{{ $item->pipeline_outcome->value }}">{{ $item->pipeline_outcome->label() }}</span>
                            <a class="collaboration-name" href="{{ route('collaborations.edit', $item) }}">{{ $item->name }}</a>
                            @if ($item->category)<p class="category-label">{{ $item->category }}</p>@endif
                            @if ($item->contact_name)<p>{{ $item->contact_name }}</p>@endif
                            @if ($item->service)<p class="muted">{{ $item->service }}</p>@endif
                            @if ($item->demo_type)<p class="muted">{{ $item->demo_type }}{{ $item->demo_date ? ' · '.$item->demo_date->format('d/m/Y') : '' }}</p>@endif
                            @if ($item->pipeline_stage === \App\Enums\PipelineStage::Contract)<p class="contract-label">{{ $item->contractType() }}</p>@endif
                            <div class="opportunity-money"><span>{{ Number::currency((float) $item->monthly_revenue, in: 'EUR', locale: 'it') }} / mese</span><span>{{ Number::currency((float) $item->one_time_revenue, in: 'EUR', locale: 'it') }} una tantum</span></div>
                            @if ($item->next_action)<p class="next-action">→ {{ $item->next_action }}</p>@endif
                            @if ($item->follow_up_date)
                                <p class="follow-up-date {{ ! $item->pipeline_outcome->closed() && $item->follow_up_date->lte(today()) ? 'follow-up-due' : '' }}">Follow-up: {{ $item->follow_up_date->format('d/m/Y') }}{{ ! $item->pipeline_outcome->closed() && $item->follow_up_date->lte(today()) ? ' · Da gestire' : '' }}</p>
                            @endif
                            <details class="pipeline-move">
                                <summary>Aggiorna fase / esito</summary>
                                <form method="POST" action="{{ route('pipeline.update', $item) }}">
                                    @csrf @method('PATCH')
                                    <label for="move-stage-{{ $item->id }}">Fase</label>
                                    <select id="move-stage-{{ $item->id }}" name="pipeline_stage">@foreach ($stages as $stage)<option value="{{ $stage->value }}" @selected($item->pipeline_stage === $stage)>{{ $stage->label() }}</option>@endforeach</select>
                                    <label for="move-outcome-{{ $item->id }}">Esito</label>
                                    <select id="move-outcome-{{ $item->id }}" name="pipeline_outcome">@foreach ($outcomes as $outcome)<option value="{{ $outcome->value }}" @selected($item->pipeline_outcome === $outcome)>{{ $outcome->label() }}</option>@endforeach</select>
                                    <label for="move-reason-{{ $item->id }}">Nota sul passaggio</label><textarea id="move-reason-{{ $item->id }}" name="outcome_reason" rows="2" maxlength="5000">{{ $item->outcome_reason }}</textarea>
                                    <button class="button button-primary button-wide">Salva passaggio</button>
                                </form>
                            </details>
                        </article>
                    @empty
                        <p class="column-empty">Nessuna opportunità in questa fase.</p>
                    @endforelse
                    @if ($column['total'] > 30)<p class="column-empty">Prime 30 opportunità. Tutte sono disponibili nell’elenco qui sotto.</p>@endif
                </section>
            @endif
        @endforeach
    </div>
    <section class="list-card pipeline-results">
        <div class="list-header"><h2>Tutte le opportunità</h2><span class="result-count">{{ $results->total() }} risultati</span></div>
        <div class="pipeline-result-list">
            @forelse ($results as $item)
                <a href="{{ route('collaborations.edit', $item) }}"><strong>{{ $item->name }}</strong><span>{{ $item->pipeline_stage->label() }} · {{ $item->pipeline_outcome->label() }}</span></a>
            @empty<p class="muted">Nessun risultato. Aggiungi un contatto o modifica i filtri.</p>@endforelse
        </div>
        {{ $results->links('components.pagination') }}
    </section>
@endsection
