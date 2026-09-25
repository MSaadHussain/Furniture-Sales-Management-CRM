<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A customer record is shared by every order they have placed, so editing one
 * order must never rewrite a customer who belongs to other orders as well.
 *
 * The phone number is the identity: change it on the order form and the order
 * moves to a different customer rather than overwriting the one on file.
 */
class CustomerIdentityOnOrderEditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $salesPerson;
    private Product $sofa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin       = User::factory()->admin()->create();
        $this->salesPerson = User::factory()->salesPerson()->create();
        $this->sofa        = Product::factory()->create([
            'name'        => 'Three seater sofa',
            'category_id' => Category::factory(),
        ]);
    }

    /** @return array<string,mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'sales_person_id'         => $this->salesPerson->id,
            'customer_name'           => 'Zakia Allouache',
            'customer_phone'          => '+33 6 61 70 11 80',
            'customer_address'        => '12 Rue de Tolbiac',
            'customer_city'           => 'Paris',
            'customer_zip_code'       => '75013',
            'requested_delivery_date' => today()->addDays(5)->toDateString(),
            'number_of_orders'        => 1,
            'payment_status'          => PaymentStatus::Pending->value,
            'order_source'            => 'Showroom Walk-in',
            'discount'                => 0,
            'delivery_charge'         => 0,
            'tax'                     => 0,
            'items' => [
                ['product_id' => $this->sofa->id, 'quantity' => 1, 'unit_price' => 40000, 'discount' => 0],
            ],
        ], $overrides);
    }

    private function createOrder(array $overrides = []): Order
    {
        $this->actingAs($this->admin)
            ->post(route('orders.store'), $this->payload($overrides))
            ->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail();
    }

    public function test_changing_the_phone_moves_the_order_to_a_new_customer(): void
    {
        $order    = $this->createOrder();
        $original = $order->customer;

        $this->actingAs($this->admin)->put(route('orders.update', $order), $this->payload([
            'customer_id'    => $original->id,   // the form still carries the old id
            'customer_name'  => 'Karim Benali',
            'customer_phone' => '+33 7 77 88 99 00',
        ]))->assertSessionHasNoErrors();

        $order->refresh();
        $original->refresh();

        $this->assertNotSame($original->id, $order->customer_id, 'The order should have moved to a new customer.');
        $this->assertSame('Karim Benali', $order->customer->name);

        // The record on file is untouched.
        $this->assertSame('Zakia Allouache', $original->name);
        $this->assertSame('+33 6 61 70 11 80', $original->phone);
        $this->assertSame(2, Customer::count());
    }

    public function test_the_other_orders_of_that_customer_are_left_alone(): void
    {
        $first  = $this->createOrder();
        $second = $this->createOrder();

        $this->assertSame($first->customer_id, $second->customer_id);
        $shared = $first->customer;

        $this->actingAs($this->admin)->put(route('orders.update', $second), $this->payload([
            'customer_id'    => $shared->id,
            'customer_name'  => 'Someone Else',
            'customer_phone' => '+33 7 00 00 00 00',
        ]))->assertSessionHasNoErrors();

        $this->assertSame($shared->id, $first->fresh()->customer_id);
        $this->assertSame('Zakia Allouache', $first->fresh()->customer->name);
        $this->assertSame('Someone Else', $second->fresh()->customer->name);
    }

    public function test_the_same_number_retyped_in_another_format_is_not_a_new_customer(): void
    {
        $order    = $this->createOrder();
        $original = $order->customer;

        $this->actingAs($this->admin)->put(route('orders.update', $order), $this->payload([
            'customer_id'    => $original->id,
            'customer_phone' => '0661701180',          // same number, written differently
            'customer_name'  => 'Zakia Allouache-Benali',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, Customer::count());
        $this->assertSame($original->id, $order->fresh()->customer_id);
        // A correction to the same person still lands on their record.
        $this->assertSame('Zakia Allouache-Benali', $original->fresh()->name);
    }

    public function test_switching_to_a_number_already_on_file_reuses_that_customer(): void
    {
        $order = $this->createOrder();
        $other = Customer::factory()->create(['name' => 'Karim Benali', 'phone' => '+33 7 22 33 44 55']);

        $this->actingAs($this->admin)->put(route('orders.update', $order), $this->payload([
            'customer_id'    => $order->customer_id,
            'customer_name'  => 'Karim Benali',
            'customer_phone' => '+33 7 22 33 44 55',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, Customer::count(), 'No duplicate should be created for a number already on file.');
        $this->assertSame($other->id, $order->fresh()->customer_id);
    }

    public function test_a_new_order_for_a_new_number_still_creates_one_customer(): void
    {
        $this->createOrder();
        $this->createOrder(['customer_name' => 'Karim Benali', 'customer_phone' => '+33 7 55 44 33 22']);

        $this->assertSame(2, Customer::count());
    }
}
