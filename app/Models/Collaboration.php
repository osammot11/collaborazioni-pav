<?php

namespace App\Models;

use App\Enums\CollaborationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];

    protected function casts(): array
    {
        return [
            'monthly_revenue' => 'decimal:2',
            'one_time_revenue' => 'decimal:2',
            'status' => CollaborationStatus::class,
            'payment_deadline' => 'date',
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
