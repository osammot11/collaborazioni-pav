<div class="field field-wide finance-section">
    <h2>Acconti, rate e saldo</h2>
    <p class="muted">Il totale riguarda l’accordo o il periodo indicato qui, non tutti i rinnovi futuri. Le rate previste non sono incassi. Il saldo determina automaticamente Pagato; un acconto determina Parzialmente pagato.</p>
    <input type="hidden" name="finance_form" value="1">
    @if ($collaboration->exists)<input type="hidden" name="expected_revision" value="{{ $collaboration->revision }}">@endif
    <div class="form-grid">
        <div class="field">
            <label for="payment_total">Totale concordato da saldare <small>Opzionale</small></label>
            <input id="payment_total" name="payment_total" type="text" inputmode="decimal" value="{{ old('payment_total', $collaboration->payment_total) }}" placeholder="Es. 3.000,00">
            @error('payment_total')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label for="payment_scope">Accordo / periodo di riferimento</label>
            <input id="payment_scope" name="payment_scope" maxlength="255" value="{{ old('payment_scope', $collaboration->payment_scope) }}" placeholder="Es. Sito + rinnovo 2026">
        </div>
    </div>
    @if ($collaboration->payment_total !== null)
        @include('collaborations._payment-summary', ['item' => $collaboration])
    @endif
    @foreach (['payment_schedule' => ['title' => 'Rate previste', 'date' => 'due_on', 'date_label' => 'Scadenza'], 'payment_entries' => ['title' => 'Pagamenti ricevuti', 'date' => 'paid_on', 'date_label' => 'Data incasso']] as $list => $config)
        <section class="finance-ledger" data-finance-ledger="{{ $list }}">
            <h3>{{ $config['title'] }}</h3>
            <p class="muted">{{ $list === 'payment_entries' ? 'Registra solo denaro già ricevuto: acconto, singole rate e saldo.' : 'Pianifica le scadenze. Non vengono segnate automaticamente come pagate.' }}</p>
            <div data-finance-rows>
            @foreach (old($list, $collaboration->$list ?? []) as $index => $entry)
                <div class="finance-row">
                    <label>Importo €<input name="{{ $list }}[{{ $index }}][amount]" inputmode="decimal" value="{{ $entry['amount'] ?? '' }}" required></label>
                    <label>{{ $config['date_label'] }}<input name="{{ $list }}[{{ $index }}][{{ $config['date'] }}]" type="date" value="{{ $entry[$config['date']] ?? '' }}" @if ($list === 'payment_entries') max="{{ today()->format('Y-m-d') }}" @endif required></label>
                    <label>Nota<input name="{{ $list }}[{{ $index }}][note]" value="{{ $entry['note'] ?? '' }}" maxlength="500" placeholder="Es. Acconto"></label>
                    <button type="button" class="button button-ghost" data-finance-remove>Rimuovi</button>
                </div>
            @endforeach
            </div>
            <template data-finance-template>
                <div class="finance-row">
                    <label>Importo €<input name="{{ $list }}[__INDEX__][amount]" inputmode="decimal" required></label>
                    <label>{{ $config['date_label'] }}<input name="{{ $list }}[__INDEX__][{{ $config['date'] }}]" type="date" @if ($list === 'payment_entries') max="{{ today()->format('Y-m-d') }}" @endif required></label>
                    <label>Nota<input name="{{ $list }}[__INDEX__][note]" maxlength="500" placeholder="Es. Acconto"></label>
                    <button type="button" class="button button-ghost" data-finance-remove>Rimuovi</button>
                </div>
            </template>
            <button type="button" class="button button-secondary" data-finance-add>Aggiungi {{ $list === 'payment_entries' ? 'incasso' : 'rata prevista' }}</button>
        </section>
    @endforeach
    <p class="muted">Le correzioni sono conservate nel registro finanziario. I rinnovi non generano automaticamente incassi o nuove rate: aggiorna il periodo e il totale concordato prima di registrarli.</p>
</div>
