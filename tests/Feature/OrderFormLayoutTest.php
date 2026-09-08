<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Layout and permission rules for the order entry form.
 */
class OrderFormLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_the_form_collects_a_postal_code_and_no_city(): void
    {
        $html = $this->actingAs($this->admin)->get(route('orders.create'))->assertOk()->getContent();

        $this->assertStringNotContainsString('name="customer_city"', $html);
        $this->assertStringContainsString('name="customer_zip_code"', $html);

        // The postal code sits in the address row, after the address field.
        $this->assertMatchesRegularExpression(
            '/name="customer_address".*name="customer_zip_code"/s',
            $html,
        );
    }

    public function test_order_status_and_notes_are_expanded_by_default(): void
    {
        $html = $this->actingAs($this->admin)->get(route('orders.create'))->assertOk()->getContent();

        $this->assertStringContainsString('showMoreOptions: true', $html);
        $this->assertStringContainsString('name="order_status"', $html);
        $this->assertStringContainsString('name="notes"', $html);
    }

    public function test_an_admin_sees_the_cancelled_option_when_creating_an_order(): void
    {
        $html = $this->actingAs($this->admin)->get(route('orders.create'))->assertOk()->getContent();

        preg_match('/<select name="order_status".*?<\/select>/s', $html, $select);
        $this->assertNotEmpty($select, 'The order status select should be on the create form.');

        $this->assertStringContainsString('value="cancelled"', $select[0]);
        $this->assertStringContainsString('value="delivered"', $select[0]);
    }

    public function test_a_manager_without_the_permission_never_sees_or_saves_cancelled(): void
    {
        $manager = User::factory()->manager()->create();

        $html = $this->actingAs($manager)->get(route('orders.create'))->assertOk()->getContent();
        preg_match('/<select name="order_status".*?<\/select>/s', $html, $select);
        $this->assertStringNotContainsString('value="cancelled"', $select[0] ?? '');

        // And posting it directly is refused, not just hidden.
        $this->actingAs($manager)
            ->post(route('orders.store'), $this->payload(['order_status' => OrderStatus::Cancelled->value]))
            ->assertSessionHasErrors('order_status');
    }

    public function test_a_manager_with_the_permission_can_create_a_cancelled_order(): void
    {
        $manager = User::factory()->manager()->create();

        Setting::put('manager_can_cancel_orders', true);
        Setting::flushMemo();

        $this->actingAs($manager)
            ->post(route('orders.store'), $this->payload(['order_status' => OrderStatus::Cancelled->value]))
            ->assertSessionHasNoErrors();
    }

    /** @return array<string,mixed> */
    private function payload(array $overrides = []): array
    {
        $product = Product::factory()->create(['category_id' => Category::factory(), 'default_price' => 50000]);

        return array_merge([
            'sales_person_id'         => User::factory()->salesPerson()->create()->id,
            'customer_name'           => 'Ahmed Khan',
            'customer_phone'          => '+92 300 1234567',
            'customer_zip_code'       => '54000',
            'requested_delivery_date' => today()->addDays(5)->toDateString(),
            'payment_status'          => 'pending',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 50000, 'discount' => 0],
            ],
        ], $overrides);
    }
}
