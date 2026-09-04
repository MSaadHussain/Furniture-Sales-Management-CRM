<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Backend enforcement of the role matrix in requirements 3 and 23 of the
 * acceptance criteria. Hiding a button is not enough; every route is checked.
 */
class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        return Order::factory()->create(['customer_id' => Customer::factory()]);
    }

    /* ----------------------------------------------------------------- */
    /* Sales Person: dashboard only                                       */
    /* ----------------------------------------------------------------- */

    public function test_a_sales_person_can_only_reach_the_dashboard(): void
    {
        $salesPerson = User::factory()->salesPerson()->create();
        $order       = $this->order();
        $customer    = Customer::factory()->create();
        $product     = Product::factory()->create(['category_id' => Category::factory()]);

        $this->actingAs($salesPerson)->get(route('dashboard'))->assertOk();

        foreach ([
            route('orders.index'),
            route('orders.create'),
            route('orders.show', $order),
            route('customers.index'),
            route('customers.show', $customer),
            route('products.index'),
            route('products.show', $product),
            route('categories.index'),
            route('colours.index'),
            route('deliveries.index'),
            route('deliveries.calendar'),
            route('reports.sales'),
            route('reports.zip'),
            route('reports.customers'),
            route('reports.products'),
            route('reports.sales-persons'),
            route('reports.deliveries'),
            route('users.index'),
            route('audit.index'),
            route('settings.index'),
        ] as $url) {
            $this->actingAs($salesPerson)->get($url)->assertForbidden();
        }
    }

    public function test_a_sales_person_cannot_create_or_change_an_order(): void
    {
        $salesPerson = User::factory()->salesPerson()->create();
        $order       = $this->order();

        $this->actingAs($salesPerson)->post(route('orders.store'), [])->assertForbidden();
        $this->actingAs($salesPerson)->put(route('orders.update', $order), [])->assertForbidden();
        $this->actingAs($salesPerson)->delete(route('orders.destroy', $order))->assertForbidden();
        $this->actingAs($salesPerson)->patch(route('orders.status', $order), ['order_status' => 'confirmed'])->assertForbidden();
    }

    public function test_the_sales_person_dashboard_hides_customer_data(): void
    {
        $salesPerson = User::factory()->salesPerson()->create();
        Customer::factory()->create(['name' => 'Confidential Buyer']);

        $this->actingAs($salesPerson)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Confidential Buyer')
            ->assertDontSee('Customers</span>', false);
    }

    /* ----------------------------------------------------------------- */
    /* Manager: operations yes, destructive/system no                     */
    /* ----------------------------------------------------------------- */

    public function test_a_manager_can_run_day_to_day_operations(): void
    {
        $manager = User::factory()->manager()->create();

        foreach ([
            route('dashboard'),
            route('orders.index'),
            route('orders.create'),
            route('customers.index'),
            route('products.index'),
            route('deliveries.index'),
            route('reports.sales'),
            route('reports.zip'),
        ] as $url) {
            $this->actingAs($manager)->get($url)->assertOk();
        }
    }

    public function test_a_manager_cannot_reach_admin_screens(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get(route('users.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('audit.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('activity.logs'))->assertForbidden();
    }

    public function test_a_manager_cannot_delete_an_order(): void
    {
        $manager = User::factory()->manager()->create();
        $order   = $this->order();

        $this->actingAs($manager)->delete(route('orders.destroy', $order))->assertForbidden();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'deleted_at' => null]);
    }

    public function test_a_manager_cannot_export_data(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get(route('orders.export'))->assertForbidden();
        $this->actingAs($manager)->get(route('customers.export'))->assertForbidden();
        $this->actingAs($manager)->get(route('products.export'))->assertForbidden();
    }

    public function test_manager_order_cancellation_is_off_until_an_admin_enables_it(): void
    {
        $manager = User::factory()->manager()->create();
        $order   = $this->order();

        $this->actingAs($manager)
            ->patch(route('orders.cancel', $order), ['cancellation_reason' => 'test'])
            ->assertForbidden();

        Setting::put('manager_can_cancel_orders', true);
        Setting::flushMemo();

        $this->actingAs($manager)
            ->patch(route('orders.cancel', $order), ['cancellation_reason' => 'test'])
            ->assertRedirect();
    }

    public function test_manager_product_management_is_off_until_an_admin_enables_it(): void
    {
        $manager  = User::factory()->manager()->create();
        $category = Category::factory()->create();

        $payload = ['name' => 'New Sofa', 'category_id' => $category->id, 'default_price' => 1000];

        $this->actingAs($manager)->post(route('products.store'), $payload)->assertForbidden();

        Setting::put('manager_can_manage_products', true);
        Setting::flushMemo();

        $this->actingAs($manager)->post(route('products.store'), $payload)->assertRedirect();
        $this->assertDatabaseHas('products', ['name' => 'New Sofa']);
    }

    /* ----------------------------------------------------------------- */
    /* Admin                                                              */
    /* ----------------------------------------------------------------- */

    public function test_an_admin_can_reach_every_screen(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([
            route('dashboard'),
            route('orders.index'),
            route('customers.index'),
            route('products.index'),
            route('categories.index'),
            route('colours.index'),
            route('deliveries.index'),
            route('deliveries.calendar'),
            route('reports.sales'),
            route('reports.products'),
            route('reports.customers'),
            route('reports.zip'),
            route('reports.sales-persons'),
            route('reports.deliveries'),
            route('users.index'),
            route('audit.index'),
            route('settings.index'),
            route('settings.security'),
            route('activity.logs'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    /* ----------------------------------------------------------------- */
    /* Account state                                                      */
    /* ----------------------------------------------------------------- */

    public function test_a_deactivated_user_is_locked_out_of_the_application(): void
    {
        $inactive = User::factory()->manager()->inactive()->create();

        $this->actingAs($inactive)->get(route('orders.index'))->assertForbidden();
    }

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('orders.index'))->assertRedirect(route('login'));
    }

    public function test_public_self_registration_is_disabled(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('register'));
        $this->post('/register', [])->assertNotFound();
    }
}
