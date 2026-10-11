<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_update_their_name(): void
    {
        $user = User::factory()->create(['name' => 'Raul Colot']);

        $response = $this->actingAs($user)
            ->from('/settings?tab=profile')
            ->patch('/settings/name', ['name' => 'Raul Colot Jr']);

        $response->assertRedirect('/settings?tab=profile');
        $response->assertSessionHas('status');

        $this->assertSame('Raul Colot Jr', $user->fresh()->name);
    }

    public function test_update_name_validates_against_empty_values(): void
    {
        $user = User::factory()->create(['name' => 'Raul Colot']);

        $this->actingAs($user)
            ->patch('/settings/name', ['name' => '   '])
            ->assertSessionHasErrors('name');

        $this->assertSame('Raul Colot', $user->fresh()->name);
    }

    public function test_update_name_rejects_values_longer_than_255_characters(): void
    {
        $user = User::factory()->create(['name' => 'Raul Colot']);

        $this->actingAs($user)
            ->patch('/settings/name', ['name' => str_repeat('a', 256)])
            ->assertSessionHasErrors('name');

        $this->assertSame('Raul Colot', $user->fresh()->name);
    }

    public function test_update_name_returns_json_when_requested(): void
    {
        $user = User::factory()->create(['name' => 'Raul Colot']);

        $response = $this->actingAs($user)
            ->patchJson('/settings/name', ['name' => 'Raul Colot Jr']);

        $response->assertOk()
            ->assertJsonPath('user.name', 'Raul Colot Jr');

        $this->assertSame('Raul Colot Jr', $user->fresh()->name);
    }

    public function test_an_unauthenticated_user_cannot_update_their_name(): void
    {
        $response = $this->patch('/settings/name', ['name' => 'Someone Else']);

        $response->assertRedirect('/login');
    }
}
