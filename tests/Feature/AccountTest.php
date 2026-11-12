<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_student_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_new_registration_creates_student_and_regenerates_session(): void
    {
        $this->post('/register', [
            'name' => 'Test Student',
            'email' => 'student@example.test',
            'password' => 'ValidSecret123!',
            'password_confirmation' => 'ValidSecret123!',
            'terms' => '1',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'student@example.test',
            'role' => 'student',
        ]);
    }

    public function test_registration_without_terms_is_rejected(): void
    {
        $this->post('/register', [
            'name' => 'Test Student',
            'email' => 'student@example.test',
            'password' => 'ValidSecret123!',
            'password_confirmation' => 'ValidSecret123!',
        ])->assertSessionHasErrors('terms');

        $this->assertGuest();
    }

    public function test_locale_switch_rejects_unknown_locale(): void
    {
        $this->post('/language', ['locale' => 'unknown'])->assertSessionHasErrors('locale');
    }
}
