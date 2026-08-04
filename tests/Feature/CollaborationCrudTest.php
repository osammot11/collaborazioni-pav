<?php

namespace Tests\Feature;

use App\Enums\CollaborationStatus;
use App\Models\Collaboration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollaborationCrudTest extends TestCase
{
    use RefreshDatabase;

    private function authorized(): static
    {
        return $this->withSession(['collaborations_authorized' => true]);
    }

    public function test_collaboration_can_be_created_with_italian_money_format(): void
    {
        $this->authorized()->post(route('collaborations.store'), [
            'name' => 'Campagna Acme',
            'description' => 'Contenuti editoriali',
            'monthly_revenue' => '1.250,50',
            'one_time_revenue' => '350,00',
            'status' => CollaborationStatus::Confirmed->value,
            'payment_deadline' => '2026-09-15',
            'notes' => 'Referente Mario',
        ])->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('collaborations', [
            'name' => 'Campagna Acme',
            'monthly_revenue' => 1250.50,
            'one_time_revenue' => 350,
            'status' => 'confermato',
        ]);
    }

    public function test_optional_fields_and_zero_revenues_are_allowed(): void
    {
        $this->authorized()->post(route('collaborations.store'), [
            'name' => 'Possibile partnership',
            'description' => '',
            'monthly_revenue' => '',
            'one_time_revenue' => '',
            'status' => 'forse',
            'payment_deadline' => '',
            'notes' => '',
        ])->assertSessionHasNoErrors();

        $collaboration = Collaboration::firstOrFail();
        $this->assertSame('0.00', $collaboration->monthly_revenue);
        $this->assertSame('0.00', $collaboration->one_time_revenue);
        $this->assertNull($collaboration->description);
        $this->assertNull($collaboration->payment_deadline);
    }

    public function test_invalid_values_are_rejected(): void
    {
        $this->authorized()->post(route('collaborations.store'), [
            'name' => '',
            'monthly_revenue' => '-20',
            'one_time_revenue' => '12,345',
            'status' => 'inventato',
            'payment_deadline' => 'non-una-data',
        ])->assertSessionHasErrors([
            'name',
            'monthly_revenue',
            'one_time_revenue',
            'status',
            'payment_deadline',
        ]);

        $this->assertDatabaseCount('collaborations', 0);
    }

    public function test_collaboration_can_be_updated_and_deleted(): void
    {
        $collaboration = Collaboration::create([
            'name' => 'Vecchio nome',
            'monthly_revenue' => 100,
            'one_time_revenue' => 0,
            'status' => CollaborationStatus::Maybe,
        ]);

        $this->authorized()->put(route('collaborations.update', $collaboration), [
            'name' => 'Nuovo nome',
            'monthly_revenue' => '250,00',
            'one_time_revenue' => '500,00',
            'status' => 'pagato',
            'payment_deadline' => '',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('collaborations', [
            'id' => $collaboration->id,
            'name' => 'Nuovo nome',
            'status' => 'pagato',
        ]);

        $this->authorized()->delete(route('collaborations.destroy', $collaboration))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('collaborations', ['id' => $collaboration->id]);
    }
}
