<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Permanent deletion of an order by an Admin.
 */
class OrderDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function orderWithItems(int $items = 2): Order
    {
        $order = Order::factory()->create(['customer_id' => Customer::factory()]);

        for ($i = 0; $i < $items; $i++) {
            OrderItem::create([
                'order_id'           => $order->id,
                'item_name_snapshot' => "Sofa {$i}",
                'quantity'           => 1,
                'unit_price'         => 5000,
                'line_total'         => 5000,
            ]);
        }

        return $order;
    }

    public function test_an_admin_deletes_the_order_completely_not_softly(): void
    {
        $order = $this->orderWithItems();

        $this->actingAs($this->admin)
            ->delete(route('orders.destroy', $order))
            ->assertRedirect(route('orders.index'));

        // Gone from the table entirely, not just flagged as deleted.
        $this->assertNull(Order::withTrashed()->find($order->id));
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_the_line_items_go_with_it(): void
    {
        $order = $this->orderWithItems(3);
        $this->assertSame(3, OrderItem::where('order_id', $order->id)->count());

        $this->actingAs($this->admin)->delete(route('orders.destroy', $order));

        $this->assertSame(0, OrderItem::where('order_id', $order->id)->count());
    }

    public function test_the_customer_and_products_survive_the_deletion(): void
    {
        $product  = Product::factory()->create(['category_id' => Category::factory()]);
        $customer = Customer::factory()->create();
        $order    = Order::factory()->create(['customer_id' => $customer->id]);

        OrderItem::create([
            'order_id'           => $order->id,
            'product_id'         => $product->id,
            'item_name_snapshot' => $product->name,
            'quantity'           => 1,
            'unit_price'         => 5000,
            'line_total'         => 5000,
        ]);

        $this->actingAs($this->admin)->delete(route('orders.destroy', $order));

        // Deleting a sale must not take the catalogue or the customer with it.
        $this->assertNotNull(Customer::find($customer->id));
        $this->assertNotNull(Product::find($product->id));
    }

    public function test_the_deletion_is_recorded_in_the_audit_log(): void
    {
        $order  = $this->orderWithItems();
        $number = $order->order_number;

        $this->actingAs($this->admin)->delete(route('orders.destroy', $order));

        $log = AuditLog::where('action', 'order.force_deleted')->firstOrFail();

        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertStringContainsString($number, $log->description);
        $this->assertSame($number, $log->old_values['order_number']);
    }

    public function test_a_deleted_order_number_is_never_handed_out_again(): void
    {
        $seller  = User::factory()->salesPerson()->create();
        $product = Product::factory()->create(['category_id' => Category::factory(), 'default_price' => 1000]);

        $payload = fn (string $phone) => [
            'sales_person_id'         => $seller->id,
            'customer_name'           => 'Buyer',
            'customer_phone'          => $phone,
            'customer_zip_code'       => '54000',
            'requested_delivery_date' => today()->addDays(3)->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000, 'discount' => 0]],
        ];

        $this->actingAs($this->admin)->post(route('orders.store'), $payload('+92 300 1111111'));
        $this->actingAs($this->admin)->post(route('orders.store'), $payload('+92 300 2222222'));

        $second = Order::orderByDesc('id')->firstOrFail();
        $usedNumber = $second->order_number;

        // Permanently delete the newest order, which removes the table maximum.
        $this->actingAs($this->admin)->delete(route('orders.destroy', $second));

        $this->actingAs($this->admin)->post(route('orders.store'), $payload('+92 300 3333333'));
        $third = Order::orderByDesc('id')->firstOrFail();

        $this->assertNotSame(
            $usedNumber,
            $third->order_number,
            'A permanently deleted order number must never be reissued.',
        );

        $year = (int) now()->format('Y');
        $this->assertSame(3, (int) Setting::get(OrderNumberService::watermarkKey($year)));
    }

    public function test_a_manager_cannot_delete_even_with_every_optional_permission_on(): void
    {
        $manager = User::factory()->manager()->create();
        $order   = $this->orderWithItems();

        foreach ([
            'manager_can_cancel_orders',
            'manager_can_manage_customers',
            'manager_can_manage_products',
            'manager_can_export_data',
        ] as $key) {
            Setting::put($key, true);
        }
        Setting::flushMemo();

        $this->actingAs($manager)
            ->delete(route('orders.destroy', $order))
            ->assertForbidden();

        $this->assertNotNull(Order::find($order->id));
    }

    public function test_a_sales_person_cannot_delete(): void
    {
        $order = $this->orderWithItems();

        $this->actingAs(User::factory()->salesPerson()->create())
            ->delete(route('orders.destroy', $order))
            ->assertForbidden();

        $this->assertNotNull(Order::find($order->id));
    }

    public function test_the_delete_button_shows_for_an_admin_only(): void
    {
        $order = $this->orderWithItems();

        $adminHtml = $this->actingAs($this->admin)
            ->get(route('orders.show', $order))->assertOk()->getContent();
        $this->assertStringContainsString('open-delete', $adminHtml);
        $this->assertStringContainsString('Delete permanently', $adminHtml);

        $managerHtml = $this->actingAs(User::factory()->manager()->create())
            ->get(route('orders.show', $order))->assertOk()->getContent();
        $this->assertStringNotContainsString('open-delete', $managerHtml);
    }

    public function test_a_cancelled_order_can_still_be_deleted(): void
    {
        $order = $this->orderWithItems();
        $order->update(['order_status' => \App\Enums\OrderStatus::Cancelled->value]);

        $this->actingAs($this->admin)
            ->delete(route('orders.destroy', $order))
            ->assertRedirect();

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }
}
