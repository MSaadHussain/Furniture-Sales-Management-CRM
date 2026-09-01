<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Colour;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $salesPerson;
    private Product $sofa;
    private Product $chair;
    private Colour $grey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->salesPerson = User::factory()->salesPerson()->create();

        $category    = Category::factory()->create(['name' => 'Sofa']);
        $this->grey  = Colour::create(['name' => 'Grey', 'hex' => '#8E8E93', 'is_active' => true]);
        $this->sofa  = Product::factory()->create(['name' => '3-Seater Sofa', 'category_id' => $category->id, 'default_price' => 85000]);
        $this->chair = Product::factory()->create(['name' => 'Dining Chair', 'category_id' => $category->id, 'default_price' => 8000]);
    }

    /** @return array<string,mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'sales_person_id'         => $this->salesPerson->id,
            'customer_name'           => 'Ahmed Khan',
            'customer_phone'          => '+92 300 1234567',
            'customer_email'          => 'ahmed@example.test',
            'customer_address'        => '12 Street 4',
            'customer_city'           => 'Lahore',
            'customer_zip_code'       => '54000',
            'requested_delivery_date' => today()->addDays(10)->toDateString(),
            'payment_status'          => PaymentStatus::Pending->value,
            'delivery_charge'         => 2500,
            'discount'                => 0,
            'tax'                     => 0,
            'items' => [
                ['product_id' => $this->sofa->id,  'colour_id' => $this->grey->id, 'quantity' => 1, 'unit_price' => 85000, 'discount' => 0],
                ['product_id' => $this->chair->id, 'colour_id' => $this->grey->id, 'quantity' => 6, 'unit_price' => 8000,  'discount' => 0],
            ],
        ], $overrides);
    }

    public function test_admin_can_create_an_order_with_multiple_items(): void
    {
        $response = $this->actingAs($this->admin)->post(route('orders.store'), $this->payload());

        $order = Order::firstOrFail();
        $response->assertRedirect(route('orders.show', $order));

        $this->assertCount(2, $order->items);
        $this->assertSame('54000', $order->zip_code);
        $this->assertSame('Grey', $order->items->first()->item_colour);
    }

    public function test_products_and_colours_created_on_the_spot_persist_in_catalogue(): void
    {
        $response = $this->actingAs($this->admin)->post(route('orders.store'), $this->payload([
            'items' => [
                [
                    'product_id'  => null,
                    'item_name'   => 'Custom Velvet Armchair',
                    'colour_id'   => null,
                    'item_colour' => 'Emerald Green',
                    'quantity'    => 2,
                    'unit_price'  => 45000,
                    'discount'    => 0,
                ],
            ],
        ]));

        $order = Order::firstOrFail();
        $response->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseHas('products', [
            'name'          => 'Custom Velvet Armchair',
            'default_price' => 45000,
            'is_active'     => true,
        ]);

        $this->assertDatabaseHas('colours', [
            'name'      => 'Emerald Green',
            'is_active' => true,
        ]);

        $product = Product::where('name', 'Custom Velvet Armchair')->firstOrFail();
        $colour  = Colour::where('name', 'Emerald Green')->firstOrFail();

        $this->assertTrue($product->colours->contains($colour));
        $this->assertSame($product->id, $order->items->first()->product_id);
        $this->assertSame($colour->id, $order->items->first()->colour_id);
    }

    public function test_totals_are_recalculated_on_the_server_and_ignore_client_values(): void
    {
        // Subtotal 85,000 + (6 x 8,000) = 133,000, plus 2,500 delivery = 135,500.
        $this->actingAs($this->admin)->post(route('orders.store'), $this->payload([
            'grand_total' => 1,        // a tampered value from the client
            'subtotal'    => 1,
        ]));

        $order = Order::firstOrFail();

        $this->assertEquals(133000, (float) $order->subtotal);
        $this->assertEquals(135500, (float) $order->grand_total);
        $this->assertEquals(135500, (float) $order->balance_due);
    }

    public function test_order_number_is_generated_sequentially_and_never_reused(): void
    {
        $this->actingAs($this->admin)->post(route('orders.store'), $this->payload());
        $this->actingAs($this->admin)->post(route('orders.store'), $this->payload(['customer_phone' => '+92 300 7654321']));

        $numbers = Order::orderBy('id')->pluck('order_number')->all();
        $year    = now()->format('Y');

        $this->assertSame(["SALE-{$year}-000001", "SALE-{$year}-000002"], $numbers);

        // Deleting the latest order must not free its number for reuse.
        Order::latest('id')->first()->delete();
        $this->actingAs($this->admin)->post(route('orders.store'), $this->payload(['customer_phone' => '+92 300 1111111']));

        $this->assertSame("SALE-{$year}-000003", Order::orderByDesc('id')->first()->order_number);
    }

    public function test_an_order_requires_at_least_one_item(): void
    {
        $this->actingAs($this->admin)
            ->post(route('orders.store'), $this->payload(['items' => []]))
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Order::count());
    }

    public function test_zip_code_is_required(): void
    {
        $this->actingAs($this->admin)
            ->post(route('orders.store'), $this->payload(['customer_zip_code' => '']))
            ->assertSessionHasErrors('customer_zip_code');
    }

    public function test_quantity_must_be_positive_and_prices_cannot_be_negative(): void
    {
        $this->actingAs($this->admin)
            ->post(route('orders.store'), $this->payload([
                'items' => [['product_id' => $this->sofa->id, 'quantity' => 0, 'unit_price' => 85000]],
            ]))
            ->assertSessionHasErrors('items.0.quantity');

        $this->actingAs($this->admin)
            ->post(route('orders.store'), $this->payload([
                'items' => [['product_id' => $this->sofa->id, 'quantity' => 1, 'unit_price' => -5]],
            ]))
            ->assertSessionHasErrors('items.0.unit_price');
    }

    public function test_order_discount_cannot_exceed_the_subtotal(): void
    {
        $this->actingAs($this->admin)
            ->post(route('orders.store'), $this->payload(['discount' => 999999]))
            ->assertSessionHasErrors('discount');
    }

    public function test_an_inactive_sales_person_cannot_be_selected(): void
    {
        $inactive = User::factory()->salesPerson()->inactive()->create();

        $this->actingAs($this->admin)
            ->post(route('orders.store'), $this->payload(['sales_person_id' => $inactive->id]))
            ->assertSessionHasErrors('sales_person_id');
    }

    public function test_a_sales_person_is_required_to_create_an_order(): void
    {
        $this->actingAs($this->admin)
            ->post(route('orders.store'), $this->payload(['sales_person_id' => '']))
            ->assertSessionHasErrors('sales_person_id');
    }

    public function test_an_admin_or_manager_cannot_be_selected_as_sales_person(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($this->admin)
            ->post(route('orders.store'), $this->payload(['sales_person_id' => $manager->id]))
            ->assertSessionHasErrors('sales_person_id');

        $this->actingAs($this->admin)
            ->post(route('orders.store'), $this->payload(['sales_person_id' => $this->admin->id]))
            ->assertSessionHasErrors('sales_person_id');
    }

    public function test_an_existing_customer_is_reused_instead_of_duplicated(): void
    {
        $existing = Customer::factory()->create(['phone' => '+92 300 1234567', 'zip_code' => '54000']);

        $this->actingAs($this->admin)->post(route('orders.store'), $this->payload([
            'customer_phone' => '03001234567',   // same number, different formatting
        ]));

        $this->assertSame(1, Customer::count());
        $this->assertSame($existing->id, Order::firstOrFail()->customer_id);
    }

    public function test_the_duplicate_lookup_endpoint_reports_an_existing_customer(): void
    {
        Customer::factory()->create(['name' => 'Repeat Buyer', 'phone' => '+92 300 5555555']);

        $this->actingAs($this->admin)
            ->getJson(route('orders.lookup.customer', ['phone' => '0300 5555555']))
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('customer.name', 'Repeat Buyer');
    }

    public function test_creation_requested_and_actual_delivery_dates_are_stored_separately(): void
    {
        $requested = today()->addDays(10);

        $this->actingAs($this->admin)->post(route('orders.store'), $this->payload([
            'requested_delivery_date' => $requested->toDateString(),
        ]));

        $order = Order::firstOrFail();
        $this->assertNull($order->actual_delivery_date);
        $this->assertTrue($order->order_created_at->isToday());

        // Record a delivery that happened before the requested date.
        $delivered = today();
        $this->actingAs($this->admin)->patch(route('orders.deliver', $order), [
            'actual_delivery_date' => $delivered->toDateString(),
        ]);

        $order->refresh();

        $this->assertSame($requested->toDateString(), $order->requested_delivery_date->toDateString());
        $this->assertSame($delivered->toDateString(), $order->actual_delivery_date->toDateString());
        $this->assertSame(OrderStatus::Delivered, $order->order_status);
    }

    public function test_an_actual_delivery_date_in_the_future_is_rejected(): void
    {
        $order = Order::factory()->create(['customer_id' => Customer::factory(), 'created_by' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->patch(route('orders.deliver', $order), ['actual_delivery_date' => today()->addDay()->toDateString()])
            ->assertSessionHasErrors('actual_delivery_date');
    }

    public function test_item_snapshots_survive_a_product_rename(): void
    {
        $this->actingAs($this->admin)->post(route('orders.store'), $this->payload());

        $this->sofa->update(['name' => 'Renamed Sofa']);

        $item = Order::firstOrFail()->items()->where('product_id', $this->sofa->id)->firstOrFail();
        $this->assertSame('3-Seater Sofa', $item->item_name_snapshot);
    }

    public function test_cancelling_an_order_records_a_reason_and_freezes_it(): void
    {
        $this->actingAs($this->admin)->post(route('orders.store'), $this->payload());
        $order = Order::firstOrFail();

        $this->actingAs($this->admin)->patch(route('orders.cancel', $order), [
            'cancellation_reason' => 'Customer changed their mind',
        ]);

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->order_status);
        $this->assertFalse($order->isEditable());

        // A frozen order can no longer be edited.
        $this->actingAs($this->admin)
            ->put(route('orders.update', $order), $this->payload())
            ->assertForbidden();
    }

    public function test_order_actions_are_written_to_the_audit_log(): void
    {
        $this->actingAs($this->admin)->post(route('orders.store'), $this->payload());
        $order = Order::firstOrFail();

        $this->actingAs($this->admin)->patch(route('orders.status', $order), [
            'order_status' => OrderStatus::Confirmed->value,
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'order.created', 'auditable_id' => $order->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.status_changed', 'auditable_id' => $order->id]);

        $statusLog = AuditLog::where('action', 'order.status_changed')->firstOrFail();
        $this->assertSame(OrderStatus::New->value, $statusLog->old_values['order_status']);
        $this->assertSame(OrderStatus::Confirmed->value, $statusLog->new_values['order_status']);
    }

    public function test_changing_the_requested_delivery_date_creates_its_own_audit_entry(): void
    {
        $this->actingAs($this->admin)->post(route('orders.store'), $this->payload());
        $order = Order::firstOrFail();

        $this->actingAs($this->admin)->put(route('orders.update', $order), $this->payload([
            'requested_delivery_date' => today()->addDays(20)->toDateString(),
        ]));

        $this->assertDatabaseHas('audit_logs', [
            'action'       => 'order.delivery_date_changed',
            'auditable_id' => $order->id,
        ]);
    }

    public function test_marking_an_order_paid_clears_the_balance(): void
    {
        $this->actingAs($this->admin)->post(route('orders.store'), $this->payload());
        $order = Order::firstOrFail();

        $this->actingAs($this->admin)->patch(route('orders.payment', $order), [
            'payment_status' => PaymentStatus::Paid->value,
            'payment_method' => 'cash',
        ]);

        $order->refresh();
        $this->assertEquals((float) $order->grand_total, (float) $order->amount_paid);
        $this->assertEquals(0.0, (float) $order->balance_due);
    }

    public function test_order_and_payment_status_are_independent(): void
    {
        $this->actingAs($this->admin)->post(route('orders.store'), $this->payload());
        $order = Order::firstOrFail();

        $this->actingAs($this->admin)->patch(route('orders.status', $order), [
            'order_status' => OrderStatus::ReadyForDelivery->value,
        ]);
        $this->actingAs($this->admin)->patch(route('orders.payment', $order), [
            'payment_status' => PaymentStatus::Paid->value,
        ]);

        $order->refresh();
        $this->assertSame(OrderStatus::ReadyForDelivery, $order->order_status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
    }

    public function test_the_order_list_supports_server_side_search(): void
    {
        $this->actingAs($this->admin)->post(route('orders.store'), $this->payload());
        $order = Order::firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('orders.index', ['search' => $order->order_number]))
            ->assertOk()
            ->assertSee($order->order_number);

        $this->actingAs($this->admin)
            ->get(route('orders.index', ['search' => 'no-such-order']))
            ->assertOk()
            ->assertDontSee($order->order_number);
    }

    public function test_orders_can_be_exported_as_csv(): void
    {
        $this->actingAs($this->admin)->post(route('orders.store'), $this->payload());

        $this->actingAs($this->admin)
            ->get(route('orders.export', ['format' => 'csv']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
