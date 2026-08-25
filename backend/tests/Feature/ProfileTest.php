<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_is_required_to_update_a_profile(): void
    {
        $this->patchJson('/api/user', [])->assertUnauthorized();
    }

    public function test_user_can_update_their_name_and_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Ancien nom',
            'email' => 'ancienne@example.test',
        ]);

        $this
            ->actingAs($user)
            ->patchJson('/api/user', [
                'name' => 'Nouveau nom',
                'email' => 'nouvelle@example.test',
            ])
            ->assertSuccessful()
            ->assertJsonPath('name', 'Nouveau nom')
            ->assertJsonPath('email', 'nouvelle@example.test');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nouveau nom',
            'email' => 'nouvelle@example.test',
        ]);
    }

    public function test_user_cannot_use_another_users_email(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this
            ->actingAs($user)
            ->patchJson('/api/user', [
                'name' => $user->name,
                'email' => $otherUser->email,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
