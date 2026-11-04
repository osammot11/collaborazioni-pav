<?php

namespace App\Models;

use App\Enums\CollaborationStatus;
use App\Enums\PipelineOutcome;
use App\Enums\PipelineStage;
use App\Enums\RecurringFrequency;
use App\Services\CollaborationFinance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Collaboration extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'monthly_revenue',
        'one_time_revenue',
        'status',
        'payment_deadline',
        'paid_at',
        'notes',
        'pipeline_stage', 'pipeline_outcome', 'category', 'contact_name', 'contact_email',
        'service', 'demo_type', 'demo_date', 'next_action', 'follow_up_date', 'outcome_reason',
        'recurring_amount', 'recurring_frequency', 'next_renewal_date', 'payment_total',
        'payment_scope', 'payment_entries', 'payment_schedule',
    ];

    protected $attributes = [
        'revision' => 1,
        'pipeline_stage' => 'da_contattare',
        'pipeline_outcome' => 'in_corso',
        'recurring_frequency' => 'mensile',
    ];

    protected static function booted(): void
    {
        static::saving(fn (self $collaboration) => $collaboration->normalizePipeline());
        static::updating(function (self $collaboration): void {
            $collaboration->revision = ((int) $collaboration->getRawOriginal('revision')) + 1;
        });
        static::created(function (self $collaboration): void {
            $collaboration->pipelineEvents()->create([
                'to_stage' => $collaboration->pipeline_stage,
                'to_outcome' => $collaboration->pipeline_outcome,
                'note' => $collaboration->outcome_reason,
            ]);
        });

        static::updated(function (self $collaboration): void {
            if ($collaboration->wasChanged(CollaborationFinance::FIELDS)) {
                $collaboration->recordFinanceChange(collect(CollaborationFinance::FIELDS)->mapWithKeys(
                    fn ($field) => [$field => $collaboration->getRawOriginal($field)]
                )->all());
            }
            if ($collaboration->wasChanged(['pipeline_stage', 'pipeline_outcome'])) {
                $collaboration->pipelineEvents()->create([
                    'from_stage' => $collaboration->getRawOriginal('pipeline_stage'),
                    'to_stage' => $collaboration->pipeline_stage,
                    'from_outcome' => $collaboration->getRawOriginal('pipeline_outcome'),
                    'to_outcome' => $collaboration->pipeline_outcome,
                    'note' => $collaboration->outcome_reason,
                ]);
            }
        });
    }

    public function normalizePipeline(): void
    {
        CollaborationFinance::normalize($this);
        $this->pipeline_outcome = $this->pipeline_stage->legacyOutcome();
        $payment = match ($this->pipeline_stage) {
            PipelineStage::Paid => 'pagato',
            PipelineStage::PartiallyPaid => 'parzialmente_pagato',
            PipelineStage::Refused => 'rifiutato',
            PipelineStage::Postponed => 'rimandato',
            default => null,
        };
        if ($payment) {
            $this->status = $payment;
        } elseif ($this->isDirty('pipeline_stage') && ! $this->isDirty('status')
            && in_array($this->getRawOriginal('pipeline_stage'), ['rifiutato', 'rimandato'], true)
            && in_array($this->status->value, ['rifiutato', 'rimandato'], true)) {
            $this->status = 'forse';
        }
        $this->next_payment_due_on = $this->nextPaymentDeadline()?->format('Y-m-d');
    }

    public function pipelineEvents(): HasMany
    {
        return $this->hasMany(PipelineEvent::class)->latest('id');
    }

    public function recordFinanceChange(array $before): void
    {
        DB::table('finance_events')->insert([
            'collaboration_id' => $this->id, 'before' => json_encode($before, JSON_THROW_ON_ERROR),
            'after' => json_encode($this->only(CollaborationFinance::FIELDS), JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function contractType(): string
    {
        $monthly = (float) $this->recurring_amount > 0;
        $oneTime = (float) $this->one_time_revenue > 0;

        return match (true) {
            $monthly && $oneTime => 'Ibrido',
            $monthly => 'Abbonamento',
            $oneTime => 'Una tantum',
            default => 'Importi da definire',
        };
    }

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'monthly_revenue' => 'decimal:2',
            'recurring_amount' => 'decimal:2',
            'recurring_frequency' => RecurringFrequency::class,
            'next_renewal_date' => 'date',
            'payment_total' => 'decimal:2',
            'payment_entries' => 'array',
            'payment_schedule' => 'array',
            'one_time_revenue' => 'decimal:2',
            'status' => CollaborationStatus::class,
            'payment_deadline' => 'date',
            'paid_at' => 'date',
            'pipeline_stage' => PipelineStage::class,
            'pipeline_outcome' => PipelineOutcome::class,
            'demo_date' => 'date',
            'follow_up_date' => 'date',
        ];
    }

    public function deadlineState(): ?string
    {
        $deadline = $this->nextPaymentDeadline();
        if (! $deadline || ! $this->status->isActionable()) {
            return null;
        }

        $today = today();

        if ($deadline->isBefore($today)) {
            return 'overdue';
        }

        if ($deadline->isSameDay($today)) {
            return 'today';
        }

        return 'upcoming';
    }

    public function receivedCents(): int
    {
        return array_sum(array_map(fn ($entry) => CollaborationFinance::cents($entry['amount']), $this->payment_entries ?? []));
    }

    public function financeSummary(): array
    {
        $total = $this->payment_total === null ? null : CollaborationFinance::cents($this->payment_total);
        $received = $this->receivedCents();

        return ['total' => $this->payment_total, 'received' => CollaborationFinance::money($received),
            'remaining' => $total === null ? null : CollaborationFinance::money(max(0, $total - $received)),
            'progress' => $total ? ($received >= $total ? 100 : (int) min(99, round($received * 100 / $total))) : 0,
            'next_due_on' => $this->nextPaymentDeadline()?->format('Y-m-d')];
    }

    public function nextPaymentDeadline(): ?Carbon
    {
        if ($this->payment_total !== null && $this->receivedCents() >= CollaborationFinance::cents($this->payment_total)) {
            return null;
        }
        // Payments cover the oldest scheduled instalments first; scheduling never counts as cash received.
        $covered = $this->receivedCents();
        foreach (collect($this->payment_schedule ?? [])->sortBy('due_on') as $entry) {
            $covered -= CollaborationFinance::cents($entry['amount']);
            if ($covered < 0) {
                return Carbon::parse($entry['due_on'])->startOfDay();
            }
        }

        return $this->payment_deadline;
    }
}
