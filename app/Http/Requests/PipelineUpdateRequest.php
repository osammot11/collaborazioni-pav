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
}
