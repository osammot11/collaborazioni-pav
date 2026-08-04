@extends('layouts.app')

@section('title', 'Nuova collaborazione')

@section('content')
    <div class="form-page-heading">
        <a href="{{ route('dashboard') }}" class="back-link">← Torna alla dashboard</a>
        <p class="eyebrow">Nuova opportunità</p>
        <h1>Aggiungi collaborazione</h1>
        <p>Inserisci i dati essenziali. Potrai modificarli in qualsiasi momento.</p>
    </div>

    <form method="POST" action="{{ route('collaborations.store') }}" class="form-card">
        @csrf
        @include('collaborations._form', ['submitLabel' => 'Salva collaborazione'])
    </form>
@endsection
