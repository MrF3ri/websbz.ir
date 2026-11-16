<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
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

    public function test_hsts_is_only_added_to_secure_requests(): void
    {
        $middleware = new SecurityHeaders;
        $httpResponse = new Response('OK');
        $httpsResponse = new Response('OK');

        $middleware->handle(
            Request::create('http://example.test/'),
            fn (): Response => $httpResponse,
        );

        $middleware->handle(
            Request::create('https://example.test/'),
            fn (): Response => $httpsResponse,
        );

        $this->assertNull($httpResponse->headers->get('Strict-Transport-Security'));
        $this->assertSame(
            'max-age=31536000',
            $httpsResponse->headers->get('Strict-Transport-Security'),
        );
    }
}
