<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeveloperApiTest extends TestCase
{
    use RefreshDatabase;

    private function developer(): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('CorrectPassword123!'),
        ]);
        $user->forceFill(['role' => 'developer'])->save();

        return $user;
    }

    public function test_guest_cannot_read_private_developer_api(): void
    {
        $this->getJson('/api/v1/categories')->assertUnauthorized();
    }

    public function test_admin_and_student_roles_cannot_access_developer_api_even_with_tokens(): void
    {
        foreach (['admin', 'student'] as $role) {
            $user = User::factory()->create();
            $user->forceFill(['role' => $role])->save();
            $token = $user->createToken('read', ['catalog:read'], now()->addDays(1));
            $this->withToken($token->plainTextToken)
                ->getJson('/api/v1/categories')->assertForbidden();
        }
    }

    public function test_developer_can_create_and_revoke_scoped_token(): void
    {
        $developer = $this->developer();
        $this->actingAs($developer)->post('/developer/tokens', [
            'name' => 'CI Acceptance Token',
            'current_password' => 'CorrectPassword123!',
            'days' => 7,
        ])->assertRedirect('/developer/tokens')->assertSessionHas('issued_token');

        $stored = $developer->tokens()->firstOrFail();
        $this->assertSame(['catalog:read'], $stored->abilities);
        $this->assertNotNull($stored->expires_at);

        $this->actingAs($developer)->delete('/developer/tokens/'.$stored->id)->assertRedirect();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $stored->id]);
    }

    public function test_token_creation_rejects_wrong_password(): void
    {
        $user = $this->developer();
        $this->actingAs($user)->post('/developer/tokens', [
            'name' => 'Unapproved Token',
            'current_password' => 'Incorrect',
            'days' => 7,
        ])->assertSessionHasErrors('current_password');

        $this->assertCount(0, $user->tokens);
    }

    public function test_valid_developer_token_only_reads_published_catalogue(): void
    {
        $this->seed(CategorySeeder::class);
        Category::where('code', 'IELTS')->update(['is_published' => false]);
        $developer = $this->developer();
        $token = $developer->createToken('Read only', ['catalog:read'], now()->addDay());

        $response = $this->withToken($token->plainTextToken)->getJson('/api/v1/categories?per_page=50');
        $response->assertOk()->assertJsonMissing(['code' => 'IELTS'])
            ->assertJsonFragment(['code' => 'TOEFL']);
    }

    public function test_expired_token_is_rejected(): void
    {
        $developer = $this->developer();
        $token = $developer->createToken('Expired token', ['catalog:read'], now()->subMinute());
        $this->withToken($token->plainTextToken)->getJson('/api/v1/cefr-levels')->assertUnauthorized();
    }

    public function test_token_without_required_ability_is_rejected(): void
    {
        $developer = $this->developer();
        $token = $developer->createToken('No abilities', [], now()->addDay());
        $this->withToken($token->plainTextToken)->getJson('/api/v1/courses')->assertForbidden();
    }
}
