@if ($errors->any())
    <div class="flash flash-error" role="alert">
        <span class="flash-icon" aria-hidden="true">!</span>
        <div><strong>Controlla i dati inseriti.</strong><br>Alcuni campi richiedono la tua attenzione.</div>
    </div>
@endif

<div class="form-grid">
    <div class="field field-wide pipeline-form-intro">
        <h2>Contatto e pipeline</h2>
        <p class="muted">La fase indica dove sei arrivato; l’esito racconta come procede la trattativa. Un contratto viene segnato automaticamente come acquisito.</p>
    </div>
    <div class="field">
        <label for="pipeline_stage">Fase commerciale</label>
        <select id="pipeline_stage" name="pipeline_stage" required>
            @foreach (\App\Enums\PipelineStage::cases() as $stage)
                <option value="{{ $stage->value }}" @selected(old('pipeline_stage', $collaboration->pipeline_stage?->value ?? 'da_contattare') === $stage->value)>{{ $stage->label() }}</option>
            @endforeach
        </select>
        @error('pipeline_stage')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field">
        <label for="pipeline_outcome">Esito commerciale</label>
        <select id="pipeline_outcome" name="pipeline_outcome" required>
            @foreach (\App\Enums\PipelineOutcome::cases() as $outcome)
                <option value="{{ $outcome->value }}" @selected(old('pipeline_outcome', $collaboration->pipeline_outcome?->value ?? 'in_corso') === $outcome->value)>{{ $outcome->label() }}</option>
            @endforeach
        </select>
        @error('pipeline_outcome')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    @foreach (['category' => 'Categoria cliente', 'contact_name' => 'Referente', 'contact_email' => 'Email del contatto', 'service' => 'Servizio proposto', 'demo_type' => 'Tipo di demo', 'demo_date' => 'Data demo', 'next_action' => 'Prossima azione', 'follow_up_date' => 'Data follow-up'] as $field => $label)
        <div class="field">
            <label for="{{ $field }}">{{ $label }} <small>Opzionale</small></label>
            <input id="{{ $field }}" name="{{ $field }}" type="{{ in_array($field, ['demo_date', 'follow_up_date']) ? 'date' : ($field === 'contact_email' ? 'email' : 'text') }}" value="{{ old($field, in_array($field, ['demo_date', 'follow_up_date']) ? $collaboration->$field?->format('Y-m-d') : $collaboration->$field) }}" maxlength="{{ $field === 'next_action' ? 500 : 255 }}" @if ($field === 'demo_type') placeholder="Sito demo, audit campagne, consulenza…" @endif>
            @error($field)<p class="field-error">{{ $message }}</p>@enderror
        </div>
    @endforeach
    <div class="field field-wide">
        <label for="outcome_reason">Contesto dell’esito <small>Opzionale</small></label>
        <textarea id="outcome_reason" name="outcome_reason" rows="2" placeholder="Es. Budget insufficiente, da ricontattare a gennaio…">{{ old('outcome_reason', $collaboration->outcome_reason) }}</textarea>
        @error('outcome_reason')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="field field-wide pipeline-form-intro"><h2>Collaborazione e rendite</h2></div>
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
        <label for="status">Situazione accordo / pagamento <span>*</span></label>
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
