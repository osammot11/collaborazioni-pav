<?php

namespace Tests\Feature;

use App\Enums\PipelineOutcome;
use App\Enums\PipelineStage;
use App\Models\Collaboration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    private function opportunity(array $data = []): Collaboration
    {
        return Collaboration::create(array_merge([
            'name' => 'Demo cliente', 'status' => 'forse',
            'monthly_revenue' => 500, 'one_time_revenue' => 1000,
            'pipeline_stage' => 'outbound_inviato', 'pipeline_outcome' => 'in_corso',
        ], $data));
    }

    public function test_pipeline_and_updates_require_access(): void
    {
        $item = $this->opportunity();
        $this->get(route('pipeline'))->assertRedirect(route('access.show'));
        $this->patch(route('pipeline.update', $item), [
            'pipeline_stage' => 'contratto', 'pipeline_outcome' => 'vinto',
        ])->assertRedirect(route('access.show'));
        $this->assertSame(PipelineStage::Outbound, $item->fresh()->pipeline_stage);
    }

    public function test_stage_and_outcome_changes_preserve_revenue_status_and_record_history(): void
    {
        $item = $this->opportunity();
        $this->withSession(['collaborations_authorized' => true])->patch(route('pipeline.update', $item), [
            'pipeline_stage' => 'preventivo_inviato', 'pipeline_outcome' => 'senza_risposta',
            'outcome_reason' => 'Nessuna risposta al preventivo',
        ])->assertRedirect(route('pipeline'));

        $item->refresh();
        $this->assertSame('forse', $item->status->value);
        $this->assertSame('500.00', $item->monthly_revenue);
        $event = $item->pipelineEvents()->first();
        $this->assertSame(PipelineStage::Outbound, $event->from_stage);
        $this->assertSame(PipelineStage::Quote, $event->to_stage);
        $this->assertSame(PipelineOutcome::NoResponse, $event->to_outcome);
        $this->assertSame('Nessuna risposta al preventivo', $event->note);
        $this->assertSame(2, $item->pipelineEvents()->count());

        $this->withSession(['collaborations_authorized' => true])->patch(route('pipeline.update', $item), [
            'pipeline_stage' => 'preventivo_inviato', 'pipeline_outcome' => 'senza_risposta',
        ]);
        $this->assertSame(2, $item->pipelineEvents()->count());
    }

    public function test_contract_is_acquired_and_can_be_reopened(): void
    {
        $item = $this->opportunity();
        $this->withSession(['collaborations_authorized' => true])->patch(route('pipeline.update', $item), [
            'pipeline_stage' => 'contratto', 'pipeline_outcome' => 'in_corso',
        ])->assertSessionHasNoErrors();
        $this->assertSame(PipelineOutcome::Won, $item->fresh()->pipeline_outcome);
        $this->assertSame('Ibrido', $item->contractType());

        $this->withSession(['collaborations_authorized' => true])->patch(route('pipeline.update', $item), [
            'pipeline_stage' => 'negoziazione', 'pipeline_outcome' => 'in_corso',
        ])->assertSessionHasNoErrors();
        $this->assertSame(PipelineStage::Negotiation, $item->fresh()->pipeline_stage);
    }

    public function test_invalid_pipeline_data_is_rejected_without_history_changes(): void
    {
        $item = $this->opportunity();
        $this->withSession(['collaborations_authorized' => true])->patch(route('pipeline.update', $item), [
            'pipeline_stage' => 'non_valido', 'pipeline_outcome' => 'vinto',
            'contact_email' => 'email errata', 'follow_up_date' => '2026-02-31',
        ])->assertSessionHasErrors(['pipeline_stage', 'pipeline_outcome', 'contact_email', 'follow_up_date']);
        $this->assertSame(1, $item->pipelineEvents()->count());
    }

    public function test_full_form_requires_outcome_when_changing_stage(): void
    {
        $item = $this->opportunity();
        $this->withSession(['collaborations_authorized' => true])->put(route('collaborations.update', $item), [
            'name' => $item->name, 'status' => 'forse', 'pipeline_stage' => 'demo_svolta',
        ])->assertSessionHasErrors('pipeline_outcome');
        $this->assertSame(PipelineStage::Outbound, $item->fresh()->pipeline_stage);
    }

    public function test_board_filters_and_due_followups_exclude_closed_opportunities(): void
    {
        $this->opportunity(['name' => 'Ristorante interessato', 'category' => 'Ristoranti', 'follow_up_date' => today()->subDay()]);
        $this->opportunity(['name' => 'Ristorante acquisito', 'category' => 'Ristoranti', 'pipeline_stage' => 'contratto', 'pipeline_outcome' => 'vinto', 'follow_up_date' => today()->subDay()]);
        $this->opportunity(['name' => 'Hotel', 'category' => 'Hotel', 'follow_up_date' => today()->addWeek()]);

        $this->withSession(['collaborations_authorized' => true])->get(route('pipeline', ['category' => 'Ristoranti', 'follow_up' => 'due']))
            ->assertOk()->assertSee('Ristorante interessato')->assertDontSee('Ristorante acquisito')
            ->assertViewHas('dueCount', 1)
            ->assertViewHas('results', fn ($results) => $results->total() === 1);
    }

    public function test_form_saves_pipeline_and_history_is_rendered(): void
    {
        $this->withSession(['collaborations_authorized' => true])->post(route('collaborations.store'), [
            'name' => 'Audit campagne', 'status' => 'forse',
            'pipeline_stage' => 'demo_programmata', 'pipeline_outcome' => 'in_corso',
            'category' => 'E-commerce', 'contact_email' => 'cliente@example.com',
            'demo_type' => 'Audit gratuito', 'demo_date' => '2026-10-15',
            'next_action' => 'Preparare audit', 'follow_up_date' => '2026-10-10',
        ])->assertSessionHasNoErrors();
        $item = Collaboration::firstOrFail();
        $this->withSession(['collaborations_authorized' => true])->get(route('collaborations.edit', $item))
            ->assertOk()->assertSee('Storico della trattativa')->assertSee('Demo programmata');
        $this->assertSame('cliente@example.com', $item->contact_email);
    }

    public function test_migration_preserves_legacy_records_and_documents_inference(): void
    {
        $migration = require database_path('migrations/2026_10_06_000000_add_pipeline_to_collaborations.php');
        $migration->down();
        foreach (['confermato', 'pagato', 'forse', 'rifiutato', 'rimandato'] as $status) {
            DB::table('collaborations')->insert(['name' => $status, 'status' => $status, 'monthly_revenue' => 100, 'notes' => 'Da preservare']);
        }
        $migration->up();
        $this->assertDatabaseCount('collaborations', 5);
        $this->assertDatabaseCount('pipeline_events', 5);
        $this->assertDatabaseHas('collaborations', ['status' => 'confermato', 'pipeline_stage' => 'contratto', 'pipeline_outcome' => 'vinto', 'notes' => 'Da preservare']);
        $this->assertDatabaseHas('collaborations', ['status' => 'rifiutato', 'pipeline_stage' => 'da_classificare', 'pipeline_outcome' => 'perso']);
        $this->assertDatabaseHas('collaborations', ['status' => 'forse', 'pipeline_stage' => 'da_classificare', 'pipeline_outcome' => 'forse']);
    }
}
