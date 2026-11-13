<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class ReleaseSecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_security_headers_are_present_without_forcing_cache_on_public_pages(): void
    {
        $response = $this->get('/');
        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        $this->assertFalse($response->headers->has('Strict-Transport-Security'),
            'HSTS must not be sent on plain HTTP in testing');
    }

    public function test_login_and_authenticated_account_responses_are_not_cacheable(): void
    {
        $response = $this->get('/login');
        $response->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));

        $student = User::factory()->create();
        $response = $this->actingAs($student)->get('/account');
        $response->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));
    }

    public function test_account_spray_from_one_ip_is_throttled_independently_of_email(): void
    {
        RateLimiter::clear('login-ip:'.hash('sha256', '127.0.0.1'));

        for ($i = 0; $i < 20; $i++) {
            $this->post('/login', [
                'email' => "not-a-user-$i@example.test",
                'password' => 'WrongPassword!'.$i,
            ])->assertSessionHasErrors('email');
        }

        $this->post('/login', [
            'email' => 'an-entirely-new-user@example.test',
            'password' => 'WrongPassword!',
        ])->assertSessionHasErrors('email');

        $ipKey = 'login-ip:'.hash('sha256', '127.0.0.1');
        $this->assertTrue(RateLimiter::tooManyAttempts($ipKey, 20));
    }
}
