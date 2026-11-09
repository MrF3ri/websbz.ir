<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Tests\TestCase;

class AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_redirects_to_dashboard_and_users_without_a_business_reach_onboarding(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        // The dashboard requires a verified account. Once verified, the tenant
        // middleware sends a user without a business to the onboarding flow.
        $user = User::query()->where('email', 'new-user@example.com')->firstOrFail();
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->get(route('dashboard'))
            ->assertRedirect(route('onboarding'));
    }

    public function test_successful_login_redirects_to_dashboard_and_then_onboarding_without_a_business(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);

        $this->get(route('dashboard'))
            ->assertRedirect(route('onboarding'));
    }

    public function test_repeated_failed_login_attempts_are_rate_limited_by_email_and_ip(): void
    {
        $email = 'throttled@example.com';
        $ip = '127.0.0.1';
        $throttleKey = Str::transliterate(Str::lower($email).'|'.$ip);

        // Pre-fill the same limiter bucket Fortify uses, so this test verifies
        // the actual login route's throttle middleware without depending on
        // multiple password-validation requests or timing.
        RateLimiter::clear($throttleKey);
        RateLimiter::increment($throttleKey, amount: 5);

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('login.store'), [
                'email' => $email,
                'password' => 'incorrect-password',
            ])
            ->assertTooManyRequests();

        RateLimiter::clear($throttleKey);
    }

    public function test_application_document_uses_persian_rtl_defaults(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<html lang="fa" dir="rtl"', escape: false);
    }
}
