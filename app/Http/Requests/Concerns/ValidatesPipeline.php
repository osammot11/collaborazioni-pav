<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PipelineStage;
use Illuminate\Validation\Rule;

trait ValidatesPipeline
{
    protected function pipelineRules(): array
    {
        return [
            'pipeline_stage' => ['sometimes', 'required', Rule::enum(PipelineStage::class)],
            'category' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'service' => ['nullable', 'string', 'max:255'],
            'demo_type' => ['nullable', 'string', 'max:255'],
            'demo_date' => ['nullable', 'date_format:Y-m-d'],
            'next_action' => ['nullable', 'string', 'max:500'],
            'follow_up_date' => ['nullable', 'date_format:Y-m-d'],
            'outcome_reason' => ['nullable', 'string', 'max:5000'],
            'payment_deadline' => ['nullable', 'date_format:Y-m-d'],
            'paid_at' => [Rule::requiredIf(fn () => $this->input('pipeline_stage', $this->route('collaboration')?->pipeline_stage?->value) === 'pagato'), 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    protected function preparePipeline(): void
    {
        $stage = $this->input('pipeline_stage', $this->route('collaboration')?->pipeline_stage?->value);
        if ($stage === PipelineStage::Refused->value) {
            $this->merge(['status' => 'rifiutato']);
        }
        if ($stage === PipelineStage::Postponed->value) {
            $this->merge(['status' => 'rimandato']);
        }
        if ($stage === PipelineStage::Paid->value) {
            $this->merge(['status' => 'pagato']);
            if (! $this->exists('paid_at') && $this->route('collaboration')?->paid_at) {
                $this->merge(['paid_at' => $this->route('collaboration')->paid_at->format('Y-m-d')]);
            }
        }
    }
}
