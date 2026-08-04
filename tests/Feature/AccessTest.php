<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['collaborations.access_code' => '1804']);
        RateLimiter::clear('collaboration-access:127.0.0.1');
    }

    public function test_dashboard_requires_access_code(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('access.show'));
    }

    public function test_wrong_access_code_is_rejected(): void
    {
        $this->post(route('access.authenticate'), ['access_code' => '9999'])
            ->assertSessionHasErrors('access_code');

        $this->assertFalse((bool) session('collaborations_authorized'));
    }

    public function test_correct_access_code_grants_access_and_logout_revokes_it(): void
    {
        $this->post(route('access.authenticate'), ['access_code' => '1804'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('collaborations_authorized', true);

        $this->withSession(['collaborations_authorized' => true])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Le tue collaborazioni');

        $this->withSession(['collaborations_authorized' => true])
            ->post(route('logout'))
            ->assertRedirect(route('access.show'));
    }

    public function test_access_is_rate_limited_after_five_wrong_attempts(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('access.authenticate'), ['access_code' => '0000']);
        }

        $this->post(route('access.authenticate'), ['access_code' => '0000'])
            ->assertSessionHasErrors('access_code');

        $this->assertStringContainsString('Troppi tentativi', session('errors')->first('access_code'));
    }
}
