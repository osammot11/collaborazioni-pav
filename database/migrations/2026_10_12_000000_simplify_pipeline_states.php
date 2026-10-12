<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('collaborations')->whereIn('pipeline_outcome', ['perso', 'in_attesa'])
                ->orderBy('id')->chunkById(100, function ($rows) {
                    foreach ($rows as $row) {
                        $stage = $row->pipeline_outcome === 'perso' ? 'rifiutato' : 'rimandato';
                        if ($row->pipeline_stage === $stage) {
                            continue;
                        }
                        DB::table('collaborations')->where('id', $row->id)->update([
                            'pipeline_stage' => $stage, 'status' => $stage,
                            'revision' => $row->revision + 1, 'updated_at' => now(),
                        ]);
                        DB::table('pipeline_events')->insert([
                            'collaboration_id' => $row->id, 'from_stage' => $row->pipeline_stage,
                            'to_stage' => $stage, 'from_outcome' => $row->pipeline_outcome,
                            'to_outcome' => $row->pipeline_outcome,
                            'note' => 'Unificazione pipeline: esito precedente convertito in stato. Dati originali conservati nello storico.',
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                });
        });
    }

    public function down(): void
    {
        // Data-only migration: preserve the new states and history on rollback.
    }
};
