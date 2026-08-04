@if ($errors->any())
    <div class="flash flash-error" role="alert">
        <span class="flash-icon" aria-hidden="true">!</span>
        <div><strong>Controlla i dati inseriti.</strong><br>Alcuni campi richiedono la tua attenzione.</div>
    </div>
@endif

<div class="form-grid">
    <div class="field field-wide">
        <label for="name">Nome <span>*</span></label>
        <input id="name" name="name" type="text" maxlength="255" required autofocus value="{{ old('name', $collaboration->name) }}" class="@error('name') is-invalid @enderror" placeholder="Es. Campagna editoriale Acme">
        @error('name')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field field-wide">
        <label for="description">Descrizione <small>Opzionale</small></label>
        <textarea id="description" name="description" rows="4" class="@error('description') is-invalid @enderror" placeholder="Una breve descrizione della collaborazione…">{{ old('description', $collaboration->description) }}</textarea>
        @error('description')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="monthly_revenue">Rendita mensile</label>
        <div class="money-input"><span>€</span><input id="monthly_revenue" name="monthly_revenue" type="text" inputmode="decimal" value="{{ old('monthly_revenue', $collaboration->exists ? str_replace('.', ',', $collaboration->monthly_revenue) : '0,00') }}" class="@error('monthly_revenue') is-invalid @enderror" placeholder="0,00"></div>
        @error('monthly_revenue')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="one_time_revenue">Rendita una tantum</label>
        <div class="money-input"><span>€</span><input id="one_time_revenue" name="one_time_revenue" type="text" inputmode="decimal" value="{{ old('one_time_revenue', $collaboration->exists ? str_replace('.', ',', $collaboration->one_time_revenue) : '0,00') }}" class="@error('one_time_revenue') is-invalid @enderror" placeholder="0,00"></div>
        @error('one_time_revenue')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="status">Situazione <span>*</span></label>
        <select id="status" name="status" required class="@error('status') is-invalid @enderror">
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('status', $collaboration->status?->value ?? 'forse') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        @error('status')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="payment_deadline">Deadline di pagamento <small>Opzionale</small></label>
        <input id="payment_deadline" name="payment_deadline" type="date" value="{{ old('payment_deadline', $collaboration->payment_deadline?->format('Y-m-d')) }}" class="@error('payment_deadline') is-invalid @enderror">
        @error('payment_deadline')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field field-wide">
        <label for="notes">Note <small>Opzionale</small></label>
        <textarea id="notes" name="notes" rows="5" class="@error('notes') is-invalid @enderror" placeholder="Dettagli, contatti, prossimi passi…">{{ old('notes', $collaboration->notes) }}</textarea>
        @error('notes')<p class="field-error">{{ $message }}</p>@enderror
    </div>
</div>

<div class="form-actions">
    <a href="{{ route('dashboard') }}" class="button button-ghost">Annulla</a>
    <button type="submit" class="button button-primary">{{ $submitLabel }} <span aria-hidden="true">→</span></button>
</div>
