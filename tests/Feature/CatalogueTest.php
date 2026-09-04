<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Colour;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Customers, products, categories and colours (requirements 7, 8).
 */
class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    /* ------------------------------ Customers ------------------------- */

    public function test_a_customer_can_be_created_searched_and_updated(): void
    {
        $this->actingAs($this->admin)->post(route('customers.store'), [
            'name'     => 'Fatima Malik',
            'phone'    => '+92 300 9998888',
            'email'    => 'fatima@example.test',
            'address'  => '5 Garden Road',
            'city'     => 'Lahore',
            'zip_code' => '54700',
        ])->assertRedirect();

        $customer = Customer::firstOrFail();
        $this->assertSame('54700', $customer->zip_code);

        // Search covers name, phone, email, ZIP and customer ID.
        foreach (['Fatima', '9998888', 'fatima@example.test', '54700', (string) $customer->id] as $term) {
            $this->actingAs($this->admin)
                ->get(route('customers.index', ['search' => $term]))
                ->assertOk()
                ->assertSee('Fatima Malik');
        }

        $this->actingAs($this->admin)->put(route('customers.update', $customer), [
            'name'     => 'Fatima Khan',
            'phone'    => $customer->phone,
            'zip_code' => '54000',
        ])->assertRedirect();

        $this->assertSame('Fatima Khan', $customer->fresh()->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.updated']);
    }

    public function test_a_customer_requires_a_name_phone_and_zip_code(): void
    {
        $this->actingAs($this->admin)
            ->post(route('customers.store'), ['name' => '', 'phone' => '', 'zip_code' => ''])
            ->assertSessionHasErrors(['name', 'phone', 'zip_code']);
    }

    public function test_a_customer_with_orders_cannot_be_deleted(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->create(['customer_id' => $customer->id]);

        $this->actingAs($this->admin)
            ->delete(route('customers.destroy', $customer))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_the_customer_profile_shows_history_and_totals(): void
    {
        $customer = Customer::factory()->create();

        Order::factory()->delivered()->create(['customer_id' => $customer->id, 'grand_total' => 100000]);
        Order::factory()->delivered()->create(['customer_id' => $customer->id, 'grand_total' => 50000]);

        $this->assertSame(2, $customer->orderCount());
        $this->assertEquals(150000.0, $customer->totalSpent());
        $this->assertNotNull($customer->firstOrderAt());
        $this->assertNotNull($customer->lastOrderAt());

        $this->actingAs($this->admin)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee($customer->name);
    }

    /* ------------------------------ Products -------------------------- */

    public function test_a_product_gets_an_automatic_code_when_none_is_given(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin)->post(route('products.store'), [
            'name'          => 'Corner Sofa',
            'category_id'   => $category->id,
            'default_price' => 145000,
            'is_active'     => 1,
        ])->assertRedirect(route('products.index'));

        $product = Product::firstOrFail();
        $this->assertMatchesRegularExpression('/^FUR-\d{4}$/', $product->product_code);
    }

    public function test_product_prices_cannot_be_negative(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('products.store'), [
                'name' => 'Broken', 'category_id' => $category->id, 'default_price' => -1,
            ])
            ->assertSessionHasErrors('default_price');
    }

    public function test_a_product_with_order_history_is_deactivated_instead_of_deleted(): void
    {
        $product = Product::factory()->create(['category_id' => Category::factory()]);
        $order   = Order::factory()->create(['customer_id' => Customer::factory()]);

        OrderItem::create([
            'order_id'           => $order->id,
            'product_id'         => $product->id,
            'item_name_snapshot' => $product->name,
            'quantity'           => 1,
            'unit_price'         => 1000,
            'line_total'         => 1000,
        ]);

        $this->actingAs($this->admin)->delete(route('products.destroy', $product));

        $this->assertNotSoftDeleted('products', ['id' => $product->id]);
        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_a_product_can_be_toggled_active(): void
    {
        $product = Product::factory()->create(['category_id' => Category::factory()]);

        $this->actingAs($this->admin)->patch(route('products.toggle', $product));
        $this->assertFalse($product->fresh()->is_active);

        $this->actingAs($this->admin)->patch(route('products.toggle', $product));
        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_a_product_colour_shortlist_narrows_the_order_form_choices(): void
    {
        $grey  = Colour::create(['name' => 'Grey', 'is_active' => true]);
        $black = Colour::create(['name' => 'Black', 'is_active' => true]);
        Colour::create(['name' => 'Walnut', 'is_active' => true]);

        $shortlisted = Product::factory()->create(['category_id' => Category::factory()]);
        $shortlisted->colours()->sync([$grey->id, $black->id]);

        $unrestricted = Product::factory()->create(['category_id' => Category::factory()]);

        $this->assertCount(2, $shortlisted->fresh()->availableColours());
        // A product with no shortlist offers every active colour.
        $this->assertCount(3, $unrestricted->fresh()->availableColours());

        $this->actingAs($this->admin)
            ->getJson(route('orders.lookup.product', $shortlisted))
            ->assertOk()
            ->assertJsonCount(2, 'colours');
    }

    /* --------------------------- Categories --------------------------- */

    public function test_a_category_can_be_created_and_gets_a_unique_slug(): void
    {
        $this->actingAs($this->admin)->post(route('categories.store'), [
            'name' => 'Coffee Table', 'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('categories', ['name' => 'Coffee Table', 'slug' => 'coffee-table']);
    }

    public function test_category_names_must_be_unique(): void
    {
        Category::factory()->create(['name' => 'Sofa']);

        $this->actingAs($this->admin)
            ->post(route('categories.store'), ['name' => 'Sofa'])
            ->assertSessionHasErrors('name');
    }

    public function test_a_category_holding_products_is_deactivated_instead_of_deleted(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $this->actingAs($this->admin)->delete(route('categories.destroy', $category));

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'is_active' => 0]);
    }

    /* ----------------------------- Colours ---------------------------- */

    public function test_a_colour_can_be_added_with_a_hex_swatch(): void
    {
        $this->actingAs($this->admin)->post(route('colours.store'), [
            'name' => 'Walnut', 'hex' => '#5C4033', 'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('colours', ['name' => 'Walnut', 'hex' => '#5C4033']);
    }

    public function test_an_invalid_hex_swatch_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('colours.store'), ['name' => 'Bad', 'hex' => 'not-a-colour'])
            ->assertSessionHasErrors('hex');
    }

    public function test_deleting_a_colour_leaves_historical_order_colours_intact(): void
    {
        $colour  = Colour::create(['name' => 'Grey', 'is_active' => true]);
        $order   = Order::factory()->create(['customer_id' => Customer::factory()]);

        $item = OrderItem::create([
            'order_id'           => $order->id,
            'colour_id'          => $colour->id,
            'item_name_snapshot' => 'Sofa',
            'item_colour'        => 'Grey',
            'quantity'           => 1,
            'unit_price'         => 1000,
            'line_total'         => 1000,
        ]);

        $this->actingAs($this->admin)->delete(route('colours.destroy', $colour));

        $item->refresh();
        $this->assertNull($item->colour_id);
        $this->assertSame('Grey', $item->item_colour);
    }
}
