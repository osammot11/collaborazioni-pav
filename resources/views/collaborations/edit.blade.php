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
@endsection
