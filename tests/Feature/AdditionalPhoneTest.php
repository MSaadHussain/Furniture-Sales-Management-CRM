<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The optional second contact number on a customer.
 */
class AdditionalPhoneTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_it_is_optional_everywhere(): void
    {
        $this->actingAs($this->admin)->post(route('customers.store'), [
            'name'     => 'No Alt Number',
            'phone'    => '+92 300 1111111',
            'zip_code' => '54000',
        ])->assertSessionHasNoErrors();

        $this->assertNull(Customer::firstOrFail()->phone_alt);
    }

    public function test_a_customer_can_be_saved_with_a_second_number(): void
    {
        $this->actingAs($this->admin)->post(route('customers.store'), [
            'name'      => 'Two Numbers',
            'phone'     => '+92 300 1111111',
            'phone_alt' => '+92 321 2222222',
            'zip_code'  => '54000',
        ])->assertSessionHasNoErrors();

        $this->assertSame('+92 321 2222222', Customer::firstOrFail()->phone_alt);
    }

    public function test_the_customer_search_matches_the_second_number(): void
    {
        Customer::factory()->create([
            'name'      => 'Findable Person',
            'phone'     => '+92 300 1111111',
            'phone_alt' => '+92 321 9998888',
        ]);

        $this->actingAs($this->admin)
            ->get(route('customers.index', ['search' => '9998888']))
            ->assertOk()
            ->assertSee('Findable Person');
    }

    public function test_duplicate_detection_matches_on_the_second_number(): void
    {
        $existing = Customer::factory()->create([
            'name'      => 'Repeat Buyer',
            'phone'     => '+92 300 1111111',
            'phone_alt' => '+92 321 5555555',
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('orders.lookup.customer', ['phone' => '0321 5555555']))
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('customer.id', $existing->id)
            ->assertJsonPath('customer.phone_alt', '+92 321 5555555');
    }

    public function test_an_order_saves_the_second_number_onto_the_customer(): void
    {
        $seller  = User::factory()->salesPerson()->create();
        $product = Product::factory()->create(['category_id' => Category::factory(), 'default_price' => 50000]);

        $this->actingAs($this->admin)->post(route('orders.store'), [
            'sales_person_id'         => $seller->id,
            'customer_name'           => 'Ordered With Alt',
            'customer_phone'          => '+92 300 4444444',
            'customer_phone_alt'      => '+92 321 7777777',
            'customer_zip_code'       => '54000',
            'requested_delivery_date' => today()->addDays(3)->toDateString(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 50000, 'discount' => 0],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('+92 321 7777777', Customer::where('name', 'Ordered With Alt')->firstOrFail()->phone_alt);
    }

    public function test_editing_an_order_without_the_field_does_not_wipe_a_stored_number(): void
    {
        $seller  = User::factory()->salesPerson()->create();
        $product = Product::factory()->create(['category_id' => Category::factory(), 'default_price' => 50000]);

        $customer = Customer::factory()->create([
            'phone'     => '+92 300 4444444',
            'phone_alt' => '+92 321 7777777',
            'zip_code'  => '54000',
        ]);

        // A payload with no customer_phone_alt key at all.
        $this->actingAs($this->admin)->post(route('orders.store'), [
            'sales_person_id'         => $seller->id,
            'customer_id'             => $customer->id,
            'customer_name'           => $customer->name,
            'customer_phone'          => $customer->phone,
            'customer_zip_code'       => $customer->zip_code,
            'requested_delivery_date' => today()->addDays(3)->toDateString(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 50000, 'discount' => 0],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('+92 321 7777777', $customer->fresh()->phone_alt);
    }

    public function test_the_field_appears_on_the_order_and_customer_forms(): void
    {
        $orderForm = $this->actingAs($this->admin)->get(route('orders.create'))->assertOk()->getContent();
        $this->assertStringContainsString('name="customer_phone_alt"', $orderForm);

        $customerForm = $this->actingAs($this->admin)->get(route('customers.create'))->assertOk()->getContent();
        $this->assertStringContainsString('name="phone_alt"', $customerForm);
    }

    public function test_the_customer_export_includes_the_second_number(): void
    {
        Customer::factory()->create(['phone_alt' => '+92 321 3333333']);

        $csv = $this->actingAs($this->admin)
            ->get(route('customers.export', ['format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Additional Phone', $csv);
        $this->assertStringContainsString('+92 321 3333333', $csv);
    }

    public function test_the_navbar_search_box_is_gone(): void
    {
        $html = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Search order number, customer, phone, ZIP', $html);
    }
}
