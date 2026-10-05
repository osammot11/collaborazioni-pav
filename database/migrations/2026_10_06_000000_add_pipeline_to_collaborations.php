<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collaborations', function (Blueprint $table) {
            $table->string('pipeline_stage')->default('da_classificare')->index();
            $table->string('pipeline_outcome')->default('in_corso')->index();
            $table->string('category')->nullable()->index();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('service')->nullable();
            $table->string('demo_type')->nullable();
            $table->date('demo_date')->nullable();
            $table->string('next_action', 500)->nullable();
            $table->date('follow_up_date')->nullable()->index();
            $table->text('outcome_reason')->nullable();
        });

        // Keep existing data without inventing outbound, demo or quote dates.
        DB::table('collaborations')->whereIn('status', ['pagato', 'confermato'])
            ->update(['pipeline_stage' => 'contratto', 'pipeline_outcome' => 'vinto']);
        DB::table('collaborations')->where('status', 'rifiutato')
            ->update(['pipeline_outcome' => 'perso']);
        DB::table('collaborations')->where('status', 'forse')
            ->update(['pipeline_outcome' => 'forse']);
        DB::table('collaborations')->where('status', 'rimandato')
            ->update(['pipeline_outcome' => 'in_attesa']);

        Schema::create('pipeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collaboration_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage')->nullable();
            $table->string('to_stage');
            $table->string('from_outcome')->nullable();
            $table->string('to_outcome');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        DB::table('collaborations')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                DB::table('pipeline_events')->insert([
                    'collaboration_id' => $row->id,
                    'to_stage' => $row->pipeline_stage,
                    'to_outcome' => $row->pipeline_outcome,
                    'note' => 'Importato dalla situazione precedente: '.$row->status.'. Fase da verificare.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_events');
        Schema::table('collaborations', function (Blueprint $table) {
            $table->dropIndex(['pipeline_stage']);
            $table->dropIndex(['pipeline_outcome']);
            $table->dropIndex(['category']);
            $table->dropIndex(['follow_up_date']);
            $table->dropColumn(['pipeline_stage', 'pipeline_outcome', 'category', 'contact_name', 'contact_email', 'service', 'demo_type', 'demo_date', 'next_action', 'follow_up_date', 'outcome_reason']);
        });
    }
};
