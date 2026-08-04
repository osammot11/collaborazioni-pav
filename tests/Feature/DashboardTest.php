<?php

namespace Tests\Feature;

use App\Enums\CollaborationStatus;
use App\Models\Collaboration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-04 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createCollaboration(array $attributes = []): Collaboration
    {
        return Collaboration::create(array_merge([
            'name' => 'Collaborazione test',
            'monthly_revenue' => 100,
            'one_time_revenue' => 200,
            'status' => CollaborationStatus::Maybe,
        ], $attributes));
    }

    public function test_dashboard_calculates_totals_by_status_and_grand_totals(): void
    {
        $this->createCollaboration(['status' => CollaborationStatus::Confirmed, 'monthly_revenue' => 1000, 'one_time_revenue' => 500]);
        $this->createCollaboration(['status' => CollaborationStatus::Confirmed, 'monthly_revenue' => 250, 'one_time_revenue' => 50]);
        $this->createCollaboration(['status' => CollaborationStatus::Maybe, 'monthly_revenue' => 300, 'one_time_revenue' => 100]);

        $this->withSession(['collaborations_authorized' => true])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('grandMonthly', 1550.0)
            ->assertViewHas('grandOneTime', 650.0)
            ->assertViewHas('totalsByStatus', function ($totals): bool {
                return $totals['confermato']['monthly'] === 1250.0
                    && $totals['confermato']['count'] === 2
                    && $totals['forse']['one_time'] === 100.0;
            });
    }

    public function test_search_and_status_filters_are_applied(): void
    {
        $this->createCollaboration(['name' => 'Acme video', 'status' => CollaborationStatus::Confirmed]);
        $this->createCollaboration(['name' => 'Beta podcast', 'status' => CollaborationStatus::Maybe]);

        $this->withSession(['collaborations_authorized' => true])
            ->get(route('dashboard', ['search' => 'Acme', 'status' => 'confermato']))
            ->assertOk()
            ->assertSee('Acme video')
            ->assertDontSee('Beta podcast');
    }

    public function test_deadline_states_and_filters_ignore_paid_and_refused_items(): void
    {
        $overdue = $this->createCollaboration(['name' => 'Da sollecitare', 'payment_deadline' => '2026-08-01']);
        $today = $this->createCollaboration(['name' => 'Scade oggi', 'payment_deadline' => '2026-08-04']);
        $future = $this->createCollaboration(['name' => 'Scade dopo', 'payment_deadline' => '2026-08-12']);
        $paid = $this->createCollaboration(['name' => 'Già pagata', 'status' => CollaborationStatus::Paid, 'payment_deadline' => '2026-08-01']);

        $this->assertSame('overdue', $overdue->deadlineState());
        $this->assertSame('today', $today->deadlineState());
        $this->assertSame('upcoming', $future->deadlineState());
        $this->assertNull($paid->deadlineState());

        $this->withSession(['collaborations_authorized' => true])
            ->get(route('dashboard', ['deadline' => 'overdue']))
            ->assertSee('Da sollecitare')
            ->assertDontSee('Già pagata')
            ->assertDontSee('Scade oggi');
    }

    public function test_dashboard_is_paginated(): void
    {
        foreach (range(1, 13) as $index) {
            $this->createCollaboration(['name' => "Collaborazione {$index}"]);
        }

        $this->withSession(['collaborations_authorized' => true])
            ->get(route('dashboard'))
            ->assertViewHas('collaborations', fn ($items): bool => $items->count() === 12 && $items->lastPage() === 2);
    }
}
