@extends('layouts.app')
@section('title', 'Accesso amministratore')
@section('content')
<section class="integration-card card">
    <p class="eyebrow">Collegamento sicuro</p>
    <h1>Autorizza ChatGPT</h1>
    <p class="muted">Usa l’account amministratore creato sulla VPS. Il codice della dashboard non autorizza applicazioni esterne.</p>
    <form method="POST" action="{{ url('/login') }}" class="access-form">
        @csrf
        <label for="email">Email</label>
        <input id="email" type="email" name="email" required autocomplete="username" autofocus>
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">
        @foreach ($errors->all() as $error)<p class="field-error">{{ $error }}</p>@endforeach
        <button class="button button-primary" type="submit">Accedi come amministratore</button>
    </form>
</section>
@endsection
