<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PipelineOutcome;
use App\Enums\PipelineStage;
use Illuminate\Validation\Rule;

trait ValidatesPipeline
{
    protected function pipelineRules(): array
    {
        return [
            'pipeline_stage' => ['sometimes', 'required', Rule::enum(PipelineStage::class)],
            'pipeline_outcome' => ['required_with:pipeline_stage', Rule::enum(PipelineOutcome::class), function ($attribute, $value, $fail) {
                $stage = $this->input('pipeline_stage', $this->route('collaboration')?->pipeline_stage?->value);
                if ($value === PipelineOutcome::Won->value && $stage !== PipelineStage::Contract->value) {
                    $fail('Per segnare un’opportunità acquisita, seleziona la fase Contratto.');
                }
            }],
            'category' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'service' => ['nullable', 'string', 'max:255'],
            'demo_type' => ['nullable', 'string', 'max:255'],
            'demo_date' => ['nullable', 'date_format:Y-m-d'],
            'next_action' => ['nullable', 'string', 'max:500'],
            'follow_up_date' => ['nullable', 'date_format:Y-m-d'],
            'outcome_reason' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function preparePipeline(): void
    {
        $stage = $this->input('pipeline_stage', $this->route('collaboration')?->pipeline_stage?->value);
        if ($stage === PipelineStage::Contract->value) {
            $this->merge(['pipeline_outcome' => PipelineOutcome::Won->value]);
        }
    }
}
