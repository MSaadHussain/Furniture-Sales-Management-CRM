<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_an_admin_can_register_a_sales_person(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name'                  => 'Ali Raza',
            'email'                 => 'ali@example.test',
            'phone'                 => '+92 300 1234567',
            'role'                  => UserRole::SalesPerson->value,
            'is_active'             => 1,
            'password'              => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'email'     => 'ali@example.test',
            'role'      => UserRole::SalesPerson->value,
            'is_active' => 1,
        ]);
    }

    public function test_passwords_are_hashed_and_never_stored_in_plain_text(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name'                  => 'Ali Raza',
            'email'                 => 'ali@example.test',
            'role'                  => UserRole::SalesPerson->value,
            'password'              => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ]);

        $stored = User::where('email', 'ali@example.test')->value('password');

        $this->assertNotSame('Str0ng-Passw0rd!', $stored);
        $this->assertTrue(Hash::check('Str0ng-Passw0rd!', $stored));
    }

    public function test_only_active_sales_persons_are_selectable_on_an_order(): void
    {
        $active   = User::factory()->salesPerson()->create(['name' => 'Active Seller']);
        $inactive = User::factory()->salesPerson()->inactive()->create(['name' => 'Inactive Seller']);

        $selectable = User::selectableSalesPersons()->pluck('id');

        $this->assertTrue($selectable->contains($active->id));
        $this->assertFalse($selectable->contains($inactive->id));

        $this->actingAs($this->admin)
            ->get(route('orders.create'))
            ->assertOk()
            ->assertSee('Active Seller')
            ->assertDontSee('Inactive Seller');
    }

    public function test_a_user_can_be_deactivated_and_reactivated(): void
    {
        $seller = User::factory()->salesPerson()->create();

        $this->actingAs($this->admin)->patch(route('users.toggle', $seller));
        $this->assertFalse($seller->fresh()->is_active);

        $this->actingAs($this->admin)->patch(route('users.toggle', $seller));
        $this->assertTrue($seller->fresh()->is_active);
    }

    public function test_a_user_cannot_deactivate_or_delete_their_own_account(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('users.toggle', $this->admin))
            ->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $this->admin))
            ->assertSessionHas('error');

        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_the_last_active_admin_cannot_be_demoted(): void
    {
        $other = User::factory()->admin()->create();

        // Demoting one of two Admins is fine.
        $this->actingAs($this->admin)->put(route('users.update', $other), [
            'name'      => $other->name,
            'email'     => $other->email,
            'role'      => UserRole::Manager->value,
            'is_active' => 1,
        ])->assertRedirect(route('users.index'));

        $this->assertSame(UserRole::Manager, $other->fresh()->role);

        // Demoting the remaining Admin is refused.
        $this->actingAs($this->admin)->put(route('users.update', $this->admin), [
            'name'      => $this->admin->name,
            'email'     => $this->admin->email,
            'role'      => UserRole::Manager->value,
            'is_active' => 1,
        ])->assertSessionHas('error');

        $this->assertSame(UserRole::Admin, $this->admin->fresh()->role);
    }

    public function test_editing_a_user_without_a_password_keeps_the_current_one(): void
    {
        $seller   = User::factory()->salesPerson()->create();
        $original = $seller->password;

        $this->actingAs($this->admin)->put(route('users.update', $seller), [
            'name'      => 'Renamed Seller',
            'email'     => $seller->email,
            'role'      => UserRole::SalesPerson->value,
            'is_active' => 1,
            'password'  => '',
        ])->assertRedirect(route('users.index'));

        $seller->refresh();
        $this->assertSame('Renamed Seller', $seller->name);
        $this->assertSame($original, $seller->password);
    }

    public function test_deleting_a_seller_keeps_their_past_order_attribution(): void
    {
        $seller = User::factory()->salesPerson()->create();
        $order  = Order::factory()->create([
            'customer_id'     => Customer::factory(),
            'sales_person_id' => $seller->id,
        ]);

        $this->actingAs($this->admin)->delete(route('users.destroy', $seller));

        $this->assertSoftDeleted('users', ['id' => $seller->id]);
        $this->assertSame($seller->id, $order->fresh()->sales_person_id);
    }

    public function test_user_changes_are_audited(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name'                  => 'Audited User',
            'email'                 => 'audited@example.test',
            'role'                  => UserRole::SalesPerson->value,
            'password'              => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created']);

        // The password must never reach the audit trail.
        $log = \App\Models\AuditLog::where('action', 'user.created')->firstOrFail();
        $this->assertArrayNotHasKey('password', $log->new_values ?? []);
    }
}
