<?php

namespace Tests\Feature;

use App\Enums\PipelineStage;
use App\Models\Collaboration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentPlanTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $fields = []): array
    {
        return array_replace([
            'name' => 'Sito e rinnovo annuale', 'status' => 'confermato', 'pipeline_stage' => 'contratto',
            'recurring_amount' => '1200.00', 'recurring_frequency' => 'annuale', 'next_renewal_date' => '2027-11-03',
            'payment_total' => '1000.00', 'payment_scope' => 'Sito 2026',
            'payment_entries' => [['amount' => '300.00', 'paid_on' => '2026-11-01', 'note' => 'Acconto']],
            'payment_schedule' => [['amount' => '300.00', 'due_on' => '2026-11-01'], ['amount' => '700.00', 'due_on' => '2026-12-01']],
        ], $fields);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 3));
        $this->withSession(['collaborations_authorized' => true]);
    }

    public function test_deposit_is_partial_and_annual_renewal_is_not_monthly(): void
    {
        $this->postJson(route('collaborations.store'), $this->plan())->assertCreated();
        $item = Collaboration::firstOrFail();
        $this->assertSame(PipelineStage::PartiallyPaid, $item->pipeline_stage);
        $this->assertSame('parzialmente_pagato', $item->status->value);
        $this->assertSame('0.00', $item->monthly_revenue);
        $this->assertSame('1200.00', $item->recurring_amount);
        $this->assertNull($item->paid_at);
        $this->assertSame(['total' => '1000.00', 'received' => '300.00', 'remaining' => '700.00',
            'progress' => 30, 'next_due_on' => '2026-12-01'], $item->financeSummary());
        $this->get(route('pipeline'))->assertOk()->assertSee('Parzialmente pagato')->assertSee('/ anno')->assertSee('Residuo');
    }

    public function test_only_actual_receipts_count_and_full_balance_sets_paid_date(): void
    {
        $this->postJson(route('collaborations.store'), $this->plan(['payment_entries' => []]))->assertCreated();
        $item = Collaboration::firstOrFail();
        $this->assertSame('0.00', $item->financeSummary()['received']);
        $this->assertSame(PipelineStage::Contract, $item->pipeline_stage);
        $entries = $this->plan()['payment_entries'];
        $entries[] = ['amount' => '700.00', 'paid_on' => '2026-11-03', 'note' => 'Saldo'];
        $data = $this->plan(['payment_entries' => $entries, 'expected_revision' => $item->revision]);
        $this->putJson(route('collaborations.update', $item), $data)->assertOk();
        $item->refresh();
        $this->assertSame(PipelineStage::Paid, $item->pipeline_stage);
        $this->assertSame('2026-11-03', $item->paid_at->format('Y-m-d'));
        $this->assertSame('0.00', $item->financeSummary()['remaining']);
        $this->assertNull($item->nextPaymentDeadline());
        $this->assertDatabaseCount('finance_events', 1);
        // A stale form cannot overwrite the ledger just saved.
        $this->putJson(route('collaborations.update', $item), $data)->assertUnprocessable()->assertJsonValidationErrors('expected_revision');
        $this->assertSame(100000, $item->fresh()->receivedCents());
    }

    public function test_cannot_mark_an_unpaid_balance_as_paid_from_the_card(): void
    {
        $this->postJson(route('collaborations.store'), $this->plan())->assertCreated();
        $item = Collaboration::firstOrFail();
        $this->patchJson(route('pipeline.update', $item), ['pipeline_stage' => 'pagato'])
            ->assertUnprocessable()->assertJsonValidationErrors('pipeline_stage');
        $this->assertSame(PipelineStage::PartiallyPaid, $item->fresh()->pipeline_stage);
    }

    public function test_invalid_or_future_receipts_and_excess_totals_are_rejected(): void
    {
        foreach ([
            ['payment_entries' => [['amount' => '1000.01', 'paid_on' => '2026-11-01']]],
            ['payment_entries' => [['amount' => '-1', 'paid_on' => '2026-11-01']]],
            ['payment_entries' => [['amount' => '1', 'paid_on' => '2026-11-04']]],
            ['payment_entries' => [['amount' => '1', 'paid_on' => '2026-02-31']]],
            ['payment_schedule' => [['amount' => '1001', 'due_on' => '2026-12-01']]],
            ['payment_total' => null], ['recurring_frequency' => 'mai'],
        ] as $invalid) {
            $this->postJson(route('collaborations.store'), $this->plan($invalid))->assertUnprocessable();
        }
        $this->assertDatabaseCount('collaborations', 0);
    }

    public function test_cent_precision_and_correction_from_fully_paid_to_partial(): void
    {
        $this->postJson(route('collaborations.store'), $this->plan([
            'payment_total' => '0,30', 'payment_schedule' => [],
            'payment_entries' => [['amount' => '0,10', 'paid_on' => '2026-11-01'], ['amount' => '0,20', 'paid_on' => '2026-11-02']],
        ]))->assertCreated();
        $item = Collaboration::firstOrFail();
        $this->assertSame('0.30', $item->financeSummary()['received']);
        $this->assertSame(PipelineStage::Paid, $item->pipeline_stage);
        $this->putJson(route('collaborations.update', $item), $this->plan([
            'expected_revision' => $item->revision, 'pipeline_stage' => 'pagato', 'payment_total' => '0.30',
            'payment_schedule' => [], 'payment_entries' => [['amount' => '0.10', 'paid_on' => '2026-11-01']],
        ]))->assertOk();
        $this->assertSame(PipelineStage::PartiallyPaid, $item->fresh()->pipeline_stage);
        $this->assertSame('0.20', $item->fresh()->financeSummary()['remaining']);
        $this->assertNull($item->fresh()->paid_at);
    }

    public function test_dashboard_keeps_annual_and_monthly_totals_separate(): void
    {
        $this->postJson(route('collaborations.store'), $this->plan())->assertCreated();
        $this->postJson(route('collaborations.store'), $this->plan(['name' => 'Mensile', 'recurring_amount' => '100', 'recurring_frequency' => 'mensile']))->assertCreated();
        $this->get(route('dashboard'))->assertOk()->assertViewHas('grandMonthly', 100.0)
            ->assertViewHas('recurringTotals', fn ($totals) => (float) $totals['annuale']['amount'] === 1200.0)
            ->assertSee('Rinnovi · Annuale');
    }

    public function test_financial_deadline_filters_follow_unpaid_instalments_and_renewals(): void
    {
        $this->postJson(route('collaborations.store'), $this->plan([
            'next_renewal_date' => '2026-11-01',
            'payment_schedule' => [['amount' => '300', 'due_on' => '2026-10-01'], ['amount' => '700', 'due_on' => '2026-11-02']],
        ]))->assertCreated();
        $item = Collaboration::firstOrFail();
        $this->assertSame('overdue', $item->deadlineState());
        $this->get(route('pipeline', ['payment_due' => 'due']))->assertViewHas('results', fn ($results) => $results->total() === 1);
        $this->get(route('pipeline', ['renewal' => 'due']))->assertViewHas('results', fn ($results) => $results->total() === 1);
        $this->get(route('dashboard', ['deadline' => 'overdue']))->assertViewHas('collaborations', fn ($results) => $results->total() === 1);
        $this->putJson(route('collaborations.update', $item), $this->plan([
            'expected_revision' => $item->revision, 'payment_entries' => [['amount' => '1000', 'paid_on' => '2026-11-03']],
            'next_renewal_date' => '2026-11-01',
        ]))->assertOk();
        $this->get(route('pipeline', ['payment_due' => 'due']))->assertViewHas('results', fn ($results) => $results->total() === 0);
        // Paying the current agreement does not mean that a future renewal was paid.
        $this->get(route('pipeline', ['renewal' => 'due']))->assertViewHas('results', fn ($results) => $results->total() === 1);
    }

    public function test_migration_preserves_legacy_revenue_and_never_invents_receipts(): void
    {
        $migration = require database_path('migrations/2026_11_03_000000_add_payment_plans_and_recurrence.php');
        $migration->down();
        DB::table('collaborations')->insert(['name' => 'Storico', 'monthly_revenue' => 100, 'status' => 'pagato', 'paid_at' => '2026-10-01']);
        $migration->up();
        $this->assertDatabaseHas('collaborations', ['name' => 'Storico', 'monthly_revenue' => 100,
            'recurring_amount' => 100, 'recurring_frequency' => 'mensile', 'payment_total' => null, 'payment_entries' => null]);
    }
}
