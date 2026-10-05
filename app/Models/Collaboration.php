<?php

namespace App\Models;

use App\Enums\CollaborationStatus;
use App\Enums\PipelineOutcome;
use App\Enums\PipelineStage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'notes',
        'pipeline_stage', 'pipeline_outcome', 'category', 'contact_name', 'contact_email',
        'service', 'demo_type', 'demo_date', 'next_action', 'follow_up_date', 'outcome_reason',
    ];

    protected $attributes = [
        'pipeline_stage' => 'da_contattare',
        'pipeline_outcome' => 'in_corso',
    ];

    protected static function booted(): void
    {
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

    public function pipelineEvents(): HasMany
    {
        return $this->hasMany(PipelineEvent::class)->latest('id');
    }

    public function contractType(): string
    {
        $monthly = (float) $this->monthly_revenue > 0;
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
            'one_time_revenue' => 'decimal:2',
            'status' => CollaborationStatus::class,
            'payment_deadline' => 'date',
            'pipeline_stage' => PipelineStage::class,
            'pipeline_outcome' => PipelineOutcome::class,
            'demo_date' => 'date',
            'follow_up_date' => 'date',
        ];
    }

    public function deadlineState(): ?string
    {
        if (! $this->payment_deadline || ! $this->status->isActionable()) {
            return null;
        }

        $today = today();

        if ($this->payment_deadline->isBefore($today)) {
            return 'overdue';
        }

        if ($this->payment_deadline->isSameDay($today)) {
            return 'today';
        }

        return 'upcoming';
    }
}
