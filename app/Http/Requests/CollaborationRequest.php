<?php

namespace App\Http\Requests;

use App\Enums\CollaborationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CollaborationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'monthly_revenue' => $this->normalizeMoney($this->input('monthly_revenue')),
            'one_time_revenue' => $this->normalizeMoney($this->input('one_time_revenue')),
            'description' => $this->emptyToNull($this->input('description')),
            'notes' => $this->emptyToNull($this->input('notes')),
            'payment_deadline' => $this->emptyToNull($this->input('payment_deadline')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'monthly_revenue' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'one_time_revenue' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'status' => ['required', Rule::enum(CollaborationStatus::class)],
            'payment_deadline' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:20000'],
        ];
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
