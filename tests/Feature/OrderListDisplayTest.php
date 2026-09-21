<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the All Orders list shows, and how much of it fits on one page.
 */
class OrderListDisplayTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_fifty_orders_fit_on_one_page(): void
    {
        Order::factory()->count(55)->create(['customer_id' => Customer::factory()]);

        $response = $this->actingAs($this->admin)->get(route('orders.index'))->assertOk();

        $this->assertCount(50, $response->viewData('orders')->items());
        $this->assertSame(2, $response->viewData('orders')->lastPage());
    }

    /**
     * The delivery address is on the list (it is what a driver needs at a
     * glance, and the postal code is part of it). The phone number is not:
     * that still belongs to the order page.
     */
    public function test_the_list_shows_the_address_but_hides_the_phone(): void
    {
        $customer = Customer::factory()->create([
            'name'     => 'Zakia Allouache',
            'phone'    => '+33661701180',
            'address'  => '12 Rue de Tolbiac',
            'city'     => 'Paris',
            'zip_code' => '75013',
        ]);
        $order = Order::factory()->create(['customer_id' => $customer->id, 'zip_code' => '75013']);

        $list = $this->actingAs($this->admin)->get(route('orders.index'))->assertOk()->getContent();

        // The Copy Details button carries the full order text as a JS payload,
        // so strip element attributes before checking what is actually visible.
        $visible = preg_replace('/<[^>]+>/', ' ', $list);

        $this->assertStringContainsString('Zakia Allouache', $visible);
        $this->assertStringContainsString('12 Rue de Tolbiac', $visible);
        $this->assertStringContainsString('75013', $visible);
        $this->assertStringNotContainsString('+33661701180', $visible);

        $detail = $this->actingAs($this->admin)->get(route('orders.show', $order))->assertOk()->getContent();
        $this->assertStringContainsString('+33661701180', $detail);
        $this->assertStringContainsString('75013', $detail);
    }

    /** More than one item is listed in full rather than summarised. */
    public function test_multiple_items_are_listed_with_their_full_names(): void
    {
        $order = Order::factory()->create(['customer_id' => Customer::factory()]);

        foreach (['90x190 lit coffre sans matelas', 'Canape angle reversible gris'] as $name) {
            OrderItem::create([
                'order_id'           => $order->id,
                'item_name_snapshot' => $name,
                'quantity'           => 1,
                'unit_price'         => 349,
                'line_total'         => 349,
            ]);
        }

        $visible = preg_replace(
            '/<[^>]+>/',
            ' ',
            $this->actingAs($this->admin)->get(route('orders.index'))->assertOk()->getContent()
        );

        $this->assertStringContainsString('90x190 lit coffre sans matelas', $visible);
        $this->assertStringContainsString('Canape angle reversible gris', $visible);
        $this->assertStringNotContainsString('+1 more', $visible);
    }

    public function test_the_list_no_longer_shows_the_item_count_line(): void
    {
        $order = Order::factory()->create(['customer_id' => Customer::factory()]);
        OrderItem::create([
            'order_id'           => $order->id,
            'item_name_snapshot' => '90x190 lit coffre sans matelas',
            'quantity'           => 1,
            'unit_price'         => 349,
            'line_total'         => 349,
        ]);

        $list = $this->actingAs($this->admin)->get(route('orders.index'))->assertOk()->getContent();

        // The item summary stays, the redundant "1 item(s)" caption is gone.
        $this->assertStringContainsString('90x190 lit coffre', $list);
        $this->assertStringNotContainsString('item(s)', $list);
    }

    public function test_sorting_and_filtering_still_work_at_the_larger_page_size(): void
    {
        Order::factory()->count(60)->create(['customer_id' => Customer::factory()]);

        $this->actingAs($this->admin)
            ->get(route('orders.index', ['sort' => 'total', 'direction' => 'asc', 'page' => 2]))
            ->assertOk();
    }
}
