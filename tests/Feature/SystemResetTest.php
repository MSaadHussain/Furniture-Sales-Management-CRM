<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemResetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role'      => UserRole::Admin,
            'is_active' => true,
            'password'  => bcrypt('SecretAdminPassword123!'),
        ]);

        $this->manager = User::factory()->create([
            'role'      => UserRole::Manager,
            'is_active' => true,
        ]);

        $this->seller = User::factory()->create([
            'role'      => UserRole::SalesPerson,
            'is_active' => true,
        ]);
    }

    public function test_non_admins_cannot_reach_system_reset_screen(): void
    {
        $this->actingAs($this->seller)
            ->get(route('settings.reset'))
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->get(route('settings.reset'))
            ->assertForbidden();
    }

    public function test_admin_can_view_system_reset_screen(): void
    {
        $this->actingAs($this->admin)
            ->get(route('settings.reset'))
            ->assertOk()
            ->assertSee('System Data Reset')
            ->assertSee('DELETE ALL DATA');
    }

    public function test_system_reset_requires_all_confirmation_fields(): void
    {
        $this->actingAs($this->admin)
            ->post(route('settings.reset.execute'), [])
            ->assertSessionHasErrors([
                'confirm_understanding',
                'confirm_irreversible',
                'confirm_phrase',
                'password',
            ]);
    }

    public function test_system_reset_rejects_incorrect_phrase(): void
    {
        $this->actingAs($this->admin)
            ->post(route('settings.reset.execute'), [
                'confirm_understanding' => '1',
                'confirm_irreversible'  => '1',
                'confirm_phrase'        => 'WRONG PHRASE',
                'password'              => 'SecretAdminPassword123!',
            ])
            ->assertSessionHasErrors(['confirm_phrase']);
    }

    public function test_system_reset_rejects_incorrect_password(): void
    {
        $this->actingAs($this->admin)
            ->post(route('settings.reset.execute'), [
                'confirm_understanding' => '1',
                'confirm_irreversible'  => '1',
                'confirm_phrase'        => 'DELETE ALL DATA',
                'password'              => 'wrong-password',
            ])
            ->assertSessionHasErrors(['password']);
    }

    public function test_system_reset_executes_successfully_and_wipes_operational_data(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id'     => $customer->id,
            'sales_person_id' => $this->seller->id,
            'created_by'      => $this->admin->id,
        ]);
        OrderItem::create([
            'order_id'              => $order->id,
            'item_name_snapshot'    => 'Test Sofa',
            'quantity'              => 1,
            'unit_price'            => 100,
            'line_total'            => 100,
        ]);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('order_items', 1);

        $response = $this->actingAs($this->admin)
            ->post(route('settings.reset.execute'), [
                'confirm_understanding' => '1',
                'confirm_irreversible'  => '1',
                'confirm_phrase'        => 'DELETE ALL DATA',
                'password'              => 'SecretAdminPassword123!',
            ]);

        $response->assertRedirect(route('dashboard'))
            ->assertSessionHas('toast');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('order_items', 0);

        // Admin account remains intact
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }
}
