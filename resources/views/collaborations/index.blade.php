@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <section class="hero-row">
        <div>
            <p class="eyebrow">Dashboard</p>
            <h1>Le tue collaborazioni</h1>
            <p class="hero-subtitle">Una vista chiara su opportunità, entrate e prossime scadenze.</p>
        </div>
        <a href="{{ route('collaborations.create') }}" class="button button-primary hero-button"><span aria-hidden="true">＋</span> Aggiungi collaborazione</a>
    </section>

    <section class="grand-total-grid" aria-label="Riepilogo complessivo">
        <article class="grand-total-card total-monthly">
            <div class="total-card-top">
                <span class="metric-icon" aria-hidden="true">↗</span>
                <span class="metric-label">Rendita mensile totale</span>
            </div>
            <strong>{{ Number::currency($grandMonthly, in: 'EUR', locale: 'it') }}</strong>
            <span class="metric-caption">Entrate ricorrenti potenziali</span>
        </article>
        <article class="grand-total-card total-onetime">
            <div class="total-card-top">
                <span class="metric-icon" aria-hidden="true">◇</span>
                <span class="metric-label">Una tantum totale</span>
            </div>
            <strong>{{ Number::currency($grandOneTime, in: 'EUR', locale: 'it') }}</strong>
            <span class="metric-caption">Valore complessivo degli accordi</span>
        </article>
    </section>

    <section class="status-section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Pipeline</p>
                <h2>Totali per situazione</h2>
            </div>
            <span class="section-hint">Mensile / Una tantum</span>
        </div>
        <div class="status-grid">
            @foreach ($totalsByStatus as $total)
                <article class="status-card {{ $total['status']->cssClass() }}">
                    <div class="status-card-heading">
                        <span class="status-dot"></span>
                        <strong>{{ $total['status']->label() }}</strong>
                        <span class="status-count">{{ $total['count'] }}</span>
                    </div>
                    <div class="status-amount">
                        <span>Mensile</span>
                        <strong>{{ Number::currency($total['monthly'], in: 'EUR', locale: 'it') }}</strong>
                    </div>
                    <div class="status-amount">
                        <span>Una tantum</span>
                        <strong>{{ Number::currency($total['one_time'], in: 'EUR', locale: 'it') }}</strong>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="list-card">
        <div class="list-header">
            <div>
                <p class="eyebrow">Archivio attivo</p>
                <h2>Collaborazioni</h2>
            </div>
            <span class="result-count">{{ $collaborations->total() }} {{ $collaborations->total() === 1 ? 'risultato' : 'risultati' }}</span>
        </div>

        <form method="GET" action="{{ route('dashboard') }}" class="filters">
            <div class="search-field">
                <span aria-hidden="true">⌕</span>
                <label class="sr-only" for="search">Cerca</label>
                <input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cerca nome, descrizione o note…">
            </div>
            <label class="sr-only" for="status">Situazione</label>
            <select id="status" name="status">
                <option value="">Tutte le situazioni</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <label class="sr-only" for="deadline">Deadline</label>
            <select id="deadline" name="deadline">
                <option value="">Tutte le deadline</option>
                <option value="overdue" @selected(($filters['deadline'] ?? '') === 'overdue')>Scadute</option>
                <option value="today" @selected(($filters['deadline'] ?? '') === 'today')>In scadenza oggi</option>
                <option value="upcoming" @selected(($filters['deadline'] ?? '') === 'upcoming')>Prossime</option>
                <option value="none" @selected(($filters['deadline'] ?? '') === 'none')>Senza deadline</option>
            </select>
            <button type="submit" class="button button-secondary">Filtra</button>
            @if (array_filter($filters))
                <a href="{{ route('dashboard') }}" class="button button-link">Azzera</a>
            @endif
        </form>

        @if ($collaborations->isEmpty())
            <div class="empty-state">
                <div class="empty-icon" aria-hidden="true">＋</div>
                <h3>{{ array_filter($filters) ? 'Nessun risultato' : 'Inizia dalla prima collaborazione' }}</h3>
                <p>{{ array_filter($filters) ? 'Prova a modificare o azzerare i filtri.' : 'Aggiungi un’opportunità per vedere qui rendite e scadenze.' }}</p>
                @if (! array_filter($filters))
                    <a href="{{ route('collaborations.create') }}" class="button button-primary">Aggiungi collaborazione</a>
                @endif
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Collaborazione</th>
                            <th>Situazione</th>
                            <th class="text-right">Mensile</th>
                            <th class="text-right">Una tantum</th>
                            <th>Deadline</th>
                            <th><span class="sr-only">Azioni</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($collaborations as $collaboration)
                            @php($deadlineState = $collaboration->deadlineState())
                            <tr>
                                <td data-label="Collaborazione">
                                    <a class="collaboration-name" href="{{ route('collaborations.edit', $collaboration) }}">{{ $collaboration->name }}</a>
                                    @if ($collaboration->description)
                                        <span class="collaboration-description">{{ Str::limit($collaboration->description, 75) }}</span>
                                    @endif
                                </td>
                                <td data-label="Situazione"><span class="status-pill {{ $collaboration->status->cssClass() }}"><span></span>{{ $collaboration->status->label() }}</span></td>
                                <td data-label="Mensile" class="money-cell text-right">{{ Number::currency((float) $collaboration->monthly_revenue, in: 'EUR', locale: 'it') }}</td>
                                <td data-label="Una tantum" class="money-cell text-right">{{ Number::currency((float) $collaboration->one_time_revenue, in: 'EUR', locale: 'it') }}</td>
                                <td data-label="Deadline">
                                    @if ($collaboration->payment_deadline)
                                        <span class="deadline deadline-{{ $deadlineState ?? 'neutral' }}">
                                            {{ $collaboration->payment_deadline->translatedFormat('d M Y') }}
                                            @if ($deadlineState === 'overdue') <small>Scaduta</small>
                                            @elseif ($deadlineState === 'today') <small>Oggi</small>
                                            @elseif ($deadlineState === 'upcoming') <small>Prossima</small>
                                            @endif
                                        </span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td class="actions-cell">
                                    <a href="{{ route('collaborations.edit', $collaboration) }}" class="icon-button" title="Modifica {{ $collaboration->name }}" aria-label="Modifica {{ $collaboration->name }}">✎</a>
                                    <form method="POST" action="{{ route('collaborations.destroy', $collaboration) }}" data-confirm="Eliminare definitivamente “{{ $collaboration->name }}”?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="icon-button icon-button-danger" title="Elimina {{ $collaboration->name }}" aria-label="Elimina {{ $collaboration->name }}">⌫</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $collaborations->links('components.pagination') }}
        @endif
    </section>
@endsection
