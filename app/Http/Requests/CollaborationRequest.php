<?php

namespace App\Http\Requests;

use App\Enums\CollaborationStatus;
use App\Http\Requests\Concerns\ValidatesPipeline;
use App\Services\CollaborationFinance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CollaborationRequest extends FormRequest
{
    use ValidatesPipeline;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->preparePipeline();
        $this->merge([
            'one_time_revenue' => $this->normalizeMoney($this->input('one_time_revenue')),
            'description' => $this->emptyToNull($this->input('description')),
            'notes' => $this->emptyToNull($this->input('notes')),
            'payment_deadline' => $this->emptyToNull($this->input('payment_deadline')),
        ]);
        foreach (['monthly_revenue', 'recurring_amount', 'payment_total'] as $field) {
            if ($this->exists($field)) {
                $this->merge([$field => $field === 'payment_total' && blank($this->input($field)) ? null : $this->normalizeMoney($this->input($field))]);
            }
        }
        foreach (['payment_entries', 'payment_schedule'] as $field) {
            if ($this->exists($field)) {
                $entries = $this->input($field);
                if (is_array($entries)) {
                    $entries = array_filter($entries, fn ($entry) => ! is_array($entry) || collect($entry)->contains(fn ($value) => filled($value)));
                    foreach ($entries as &$entry) {
                        if (is_array($entry) && array_key_exists('amount', $entry)) {
                            $entry['amount'] = $this->normalizeMoney($entry['amount']);
                        }
                    }
                    unset($entry);
                    $this->merge([$field => array_values($entries)]);
                }
            }
        }
        // HTML forms need an explicit marker to submit an empty ledger.
        if ($this->boolean('finance_form')) {
            $this->merge(['payment_entries' => $this->input('payment_entries', []), 'payment_schedule' => $this->input('payment_schedule', [])]);
        }
    }

    public function rules(): array
    {
        return array_merge($this->pipelineRules(), CollaborationFinance::rules(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'monthly_revenue' => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2',
                Rule::prohibitedIf($this->exists('recurring_amount') || $this->exists('recurring_frequency'))],
            'expected_revision' => [Rule::requiredIf(fn () => $this->route('collaboration') && ($this->exists('payment_entries') || $this->exists('payment_schedule') || $this->exists('payment_total'))), 'integer', 'min:1'],
            'one_time_revenue' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'status' => ['required', Rule::enum(CollaborationStatus::class)],
            'payment_deadline' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:20000'],
        ]);
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Il nome è obbligatorio.',
            'name.max' => 'Il nome non può superare 255 caratteri.',
            '*.numeric' => 'Inserisci un importo valido.',
            '*.min' => 'L’importo non può essere negativo.',
            '*.max' => 'L’importo è troppo elevato.',
            '*.decimal' => 'Usa al massimo due cifre decimali.',
            'status.*' => 'Seleziona una situazione valida.',
            'payment_deadline.date_format' => 'Inserisci una deadline valida.',
            'paid_at.required' => 'Per la fase Pagato inserisci la data effettiva dell’incasso.',
            'paid_at.date_format' => 'Inserisci una data di pagamento valida.',
            'paid_at.before_or_equal' => 'La data effettiva del pagamento non può essere futura.',
            'payment_total.gt' => 'Il totale concordato deve essere maggiore di zero, oppure lasciato vuoto.',
            'payment_entries.*.amount.gt' => 'Ogni incasso deve essere maggiore di zero.',
            'payment_entries.*.paid_on.required' => 'Indica la data di ogni incasso.',
            'payment_entries.*.paid_on.date_format' => 'Inserisci una data valida per ogni incasso.',
            'payment_entries.*.paid_on.before_or_equal' => 'Gli incassi effettivi non possono avere una data futura.',
            'payment_schedule.*.due_on.required' => 'Indica la scadenza di ogni rata prevista.',
            'payment_schedule.*.due_on.date_format' => 'Inserisci una scadenza valida per ogni rata prevista.',
        ];
    }

    private function normalizeMoney(mixed $value): string
    {
        if ($value === null || trim((string) $value) === '') {
            return '0';
        }

        $value = str_replace(["\u{00A0}", ' ', '€'], '', trim((string) $value));

        if (str_contains($value, ',') && str_contains($value, '.')) {
            return str_replace(',', '.', str_replace('.', '', $value));
        }

        if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $value) === 1) {
            return str_replace('.', '', $value);
        }

        return str_replace(',', '.', $value);
    }

    private function emptyToNull(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : $value;
    }
}
