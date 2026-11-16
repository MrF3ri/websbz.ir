<?php

namespace Tests\Feature\Security;

use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class SessionCookieSecurityTest extends TestCase
{
    public function test_session_cookie_uses_secure_http_only_and_lax_flags_when_configured(): void
    {
        config([
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
        ]);

        $response = $this->get(route('login'));

        $sessionCookie = collect($response->headers->getCookies())
            ->first(fn (Cookie $cookie): bool => $cookie->getName() === config('session.cookie'));

        $this->assertNotNull($sessionCookie);
        $this->assertTrue($sessionCookie->isSecure());
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->assertSame('lax', $sessionCookie->getSameSite());
    }
}
