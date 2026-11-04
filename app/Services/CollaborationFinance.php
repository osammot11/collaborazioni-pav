<?php

namespace App\Services;

use App\Enums\PipelineStage;
use App\Enums\RecurringFrequency;
use App\Models\Collaboration;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CollaborationFinance
{
    public const FIELDS = ['recurring_amount', 'recurring_frequency', 'next_renewal_date',
        'payment_total', 'payment_scope', 'payment_entries', 'payment_schedule'];

    public static function cents(string|int|float $amount): int
    {
        $parts = explode('.', (string) $amount, 2);

        return ((int) $parts[0] * 100) + (int) str_pad(substr($parts[1] ?? '', 0, 2), 2, '0');
    }

    public static function money(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function rules(): array
    {
        $amount = ['numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'];

        return [
            'recurring_amount' => ['sometimes', 'required', ...$amount],
            'recurring_frequency' => ['sometimes', 'required', Rule::enum(RecurringFrequency::class)],
            'next_renewal_date' => ['nullable', 'date_format:Y-m-d'],
            'payment_total' => ['nullable', ...$amount, 'gt:0'],
            'payment_scope' => ['nullable', 'string', 'max:255'],
            'payment_entries' => ['sometimes', 'array', 'list', 'max:200'],
            'payment_entries.*' => ['array:amount,paid_on,note'],
            'payment_entries.*.amount' => ['required', ...$amount, 'gt:0'],
            'payment_entries.*.paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'payment_entries.*.note' => ['nullable', 'string', 'max:500'],
            'payment_schedule' => ['sometimes', 'array', 'list', 'max:200'],
            'payment_schedule.*' => ['array:amount,due_on,note'],
            'payment_schedule.*.amount' => ['required', ...$amount, 'gt:0'],
            'payment_schedule.*.due_on' => ['required', 'date_format:Y-m-d'],
            'payment_schedule.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public static function normalize(Collaboration $item): void
    {
        // Old integrations may still send monthly_revenue; never silently accept conflicting values.
        if ($item->isDirty('monthly_revenue') && ! $item->isDirty('recurring_amount')) {
            $item->recurring_amount = $item->monthly_revenue;
            $item->recurring_frequency = RecurringFrequency::Monthly;
        }
        if ($item->recurring_amount === null) {
            $item->recurring_amount = $item->monthly_revenue ?? '0.00';
        }
        $item->monthly_revenue = $item->recurring_frequency === RecurringFrequency::Monthly ? $item->recurring_amount : '0.00';

        $values = $item->only(self::FIELDS);
        $values['recurring_frequency'] = $item->recurring_frequency->value;
        $values['next_renewal_date'] = $item->next_renewal_date?->format('Y-m-d');
        $values['payment_entries'] ??= [];
        $values['payment_schedule'] ??= [];
        Validator::make($values, self::rules())->validate();
        $received = $item->receivedCents();
        $scheduled = array_sum(array_map(fn ($entry) => self::cents($entry['amount']), $item->payment_schedule ?? []));
        if ($item->payment_total === null) {
            if ($received > 0 || $scheduled > 0 || $item->pipeline_stage === PipelineStage::PartiallyPaid) {
                throw ValidationException::withMessages(['payment_total' => 'Indica il totale concordato prima di registrare rate o incassi.']);
            }

            return; // Preserve historical paid records without inventing an amount or payment.
        }
        $total = self::cents($item->payment_total);
        if ($received > $total || $scheduled > $total) {
            throw ValidationException::withMessages(['payment_total' => 'Incassi e piano rate, separatamente, non possono superare il totale concordato.']);
        }
        if (in_array($item->pipeline_stage, [PipelineStage::Refused, PipelineStage::Postponed], true)) {
            return; // Keep cash history even for a paused/cancelled project.
        }
        if ($received === $total) {
            $item->pipeline_stage = PipelineStage::Paid;
            $item->status = 'pagato';
            $dates = array_column($item->payment_entries ?? [], 'paid_on');
            $item->paid_at = max($dates);
        } elseif ($received > 0) {
            if ($item->pipeline_stage === PipelineStage::Paid && $item->isDirty('pipeline_stage')) {
                throw ValidationException::withMessages(['pipeline_stage' => 'Il pagamento è parziale: manca ancora il saldo.']);
            }
            $item->pipeline_stage = PipelineStage::PartiallyPaid;
            $item->status = 'parzialmente_pagato';
            $item->paid_at = null;
        } else {
            if ($item->pipeline_stage === PipelineStage::Paid || $item->pipeline_stage === PipelineStage::PartiallyPaid) {
                if ($item->isDirty('pipeline_stage') || ! $item->exists) {
                    throw ValidationException::withMessages(['pipeline_stage' => 'Registra gli incassi effettivi prima di segnare il pagamento.']);
                }
                $item->pipeline_stage = PipelineStage::Contract;
                $item->status = 'confermato';
                $item->paid_at = null;
            } elseif ($item->status->value === 'pagato' || $item->status->value === 'parzialmente_pagato') {
                $item->status = 'confermato';
            }
        }
    }
}
