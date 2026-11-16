<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_baseline_security_headers_are_present_on_web_and_health_responses(): void
    {
        foreach ([route('home'), '/up'] as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Permissions-Policy', 'camera=(), microphone=()');
        }
    }

    public function test_hsts_is_only_sent_for_secure_requests(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');

        $this->withServerVariables(['HTTPS' => 'on'])
            ->get(route('home'))
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
