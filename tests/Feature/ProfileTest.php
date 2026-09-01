<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_profile_page_is_displayed(): void
    {
        $user = User::factory()->salesPerson()->create();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->salesPerson()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name'  => 'Updated Name',
                'email' => 'updated@example.test',
                'phone' => '+92 300 1112223',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('updated@example.test', $user->email);
        $this->assertSame('+92 300 1112223', $user->phone);
    }

    public function test_a_user_can_change_their_own_password(): void
    {
        $user = User::factory()->salesPerson()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password'      => 'old-password',
                'password'              => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_the_current_password_is_required_to_change_it(): void
    {
        $user = User::factory()->salesPerson()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password'      => 'wrong-password',
                'password'              => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    /**
     * Accounts are managed by an Admin, so there is deliberately no route for a
     * user to delete their own account.
     */
    public function test_a_user_cannot_delete_their_own_account(): void
    {
        $user = User::factory()->salesPerson()->create();

        $this->actingAs($user)->delete('/profile')->assertStatus(405);
        $this->assertNotNull($user->fresh());
    }
}
