<?php

namespace App\Models;

use App\Enums\PipelineOutcome;
use App\Enums\PipelineStage;
use Illuminate\Database\Eloquent\Model;

class PipelineEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'from_stage' => PipelineStage::class,
            'to_stage' => PipelineStage::class,
            'from_outcome' => PipelineOutcome::class,
            'to_outcome' => PipelineOutcome::class,
        ];
    }
}
