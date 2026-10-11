<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPipeline;
use Illuminate\Foundation\Http\FormRequest;

class PipelineUpdateRequest extends FormRequest
{
    use ValidatesPipeline;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->preparePipeline();
    }

    public function rules(): array
    {
        $rules = $this->pipelineRules();
        $rules['pipeline_stage'][0] = 'required';
        $rules['pipeline_outcome'][0] = 'required';

        return $rules;
    }

    public function messages(): array
    {
        return [
            'paid_at.required' => 'Per la fase Pagato inserisci la data effettiva dell’incasso.',
            'paid_at.date_format' => 'Inserisci una data di pagamento valida.',
            'paid_at.before_or_equal' => 'La data effettiva del pagamento non può essere futura.',
            'payment_deadline.date_format' => 'Inserisci una scadenza di pagamento valida.',
        ];
    }
}
