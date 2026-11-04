@php($payment = $item->financeSummary())
<div class="payment-summary">
    @if ($item->payment_scope)<p class="muted">{{ $item->payment_scope }}</p>@endif
    <div class="payment-totals">
        <span>Concordato <strong>{{ Number::currency((float) $payment['total'], in: 'EUR', locale: 'it') }}</strong></span>
        <span>Incassato <strong>{{ Number::currency((float) $payment['received'], in: 'EUR', locale: 'it') }}</strong></span>
        <span>Residuo <strong>{{ Number::currency((float) $payment['remaining'], in: 'EUR', locale: 'it') }}</strong></span>
    </div>
    <progress max="100" value="{{ $payment['progress'] }}" aria-label="Percentuale incassata">{{ $payment['progress'] }}%</progress>
    @if ($item->nextPaymentDeadline())<p class="follow-up-date {{ in_array($item->deadlineState(), ['overdue', 'today']) ? 'follow-up-due' : '' }}">Prossima rata / saldo: {{ $item->nextPaymentDeadline()->format('d/m/Y') }}</p>@endif
</div>
