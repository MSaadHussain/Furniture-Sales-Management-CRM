<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Colour;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComprehensiveExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $salesPerson;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->manager = User::factory()->manager()->create();
        $this->salesPerson = User::factory()->salesPerson()->create();

        $category = Category::create(['name' => 'Living Room', 'sort_order' => 1, 'is_active' => true]);
        $colour = Colour::create(['name' => 'Beige', 'hex' => '#F5F5DC', 'sort_order' => 1, 'is_active' => true]);

        $product = Product::create([
            'name' => 'Corner Sofa',
            'product_code' => 'SOF-001',
            'category_id' => $category->id,
            'default_price' => 850,
            'is_active' => true,
        ]);
        $product->colours()->attach($colour->id);

        $customer = Customer::create([
            'name' => 'Alice Martin',
            'phone' => '0612345678',
            'email' => 'alice@example.com',
            'address' => '123 Main Street',
            'city' => 'Paris',
            'state' => 'IDF',
            'zip_code' => '75001',
        ]);

        $order = Order::create([
            'order_number' => 'SALE-2026-000001',
            'customer_id' => $customer->id,
            'sales_person_id' => $this->salesPerson->id,
            'order_created_at' => today(),
            'requested_delivery_date' => today()->addDays(3),
            'actual_delivery_date' => null,
            'order_status' => 'new',
            'payment_status' => 'pending',
            'subtotal' => 850,
            'discount' => 0,
            'delivery_charge' => 0,
            'tax' => 0,
            'grand_total' => 850,
            'amount_paid' => 0,
            'balance_due' => 850,
            'zip_code' => '75001',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'colour_id' => $colour->id,
            'item_name_snapshot' => 'Corner Sofa',
            'item_colour' => 'Beige',
            'quantity' => 1,
            'unit_price' => 850,
            'discount' => 0,
            'line_total' => 850,
        ]);

        AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'order.created',
            'auditable_type' => Order::class,
            'auditable_id' => $order->id,
            'description' => 'Created order SALE-2026-000001',
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_orders_export_csv(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('orders.export', ['format' => 'csv']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=orders-', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_orders_export_xlsx(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('orders.export', ['format' => 'xlsx']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('attachment; filename=orders-', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_customers_export_csv(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('customers.export', ['format' => 'csv']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=customers-', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_customers_export_xlsx(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('customers.export', ['format' => 'xlsx']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('attachment; filename=customers-', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_products_export_csv(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('products.export', ['format' => 'csv']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=products-', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_products_export_xlsx(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('products.export', ['format' => 'xlsx']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('attachment; filename=products-', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_deliveries_export_csv(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('deliveries.export', ['format' => 'csv']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=deliveries-', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_deliveries_export_xlsx(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('deliveries.export', ['format' => 'xlsx']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('attachment; filename=deliveries-', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_audit_logs_export_csv(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('audit.export', ['format' => 'csv']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=audit-log-', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_audit_logs_export_xlsx(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('audit.export', ['format' => 'xlsx']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('attachment; filename=audit-log-', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_all_reports_export_formats(): void
    {
        $reports = ['sales', 'products', 'customers', 'zip', 'sales-persons', 'deliveries'];

        foreach ($reports as $report) {
            $csv = $this->actingAs($this->admin)
                ->get(route('reports.export', ['report' => $report, 'format' => 'csv']));
            $csv->assertOk();
            $csv->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

            $xlsx = $this->actingAs($this->admin)
                ->get(route('reports.export', ['report' => $report, 'format' => 'xlsx']));
            $xlsx->assertOk();
            $xlsx->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
    }

    public function test_unauthorized_users_cannot_export(): void
    {
        // Unauthenticated guest
        $this->get(route('orders.export'))->assertRedirect(route('login'));
        $this->get(route('customers.export'))->assertRedirect(route('login'));
        $this->get(route('products.export'))->assertRedirect(route('login'));
        $this->get(route('deliveries.export'))->assertRedirect(route('login'));

        // Sales Person
        $this->actingAs($this->salesPerson)->get(route('orders.export'))->assertForbidden();
        $this->actingAs($this->salesPerson)->get(route('customers.export'))->assertForbidden();
        $this->actingAs($this->salesPerson)->get(route('products.export'))->assertForbidden();
        $this->actingAs($this->salesPerson)->get(route('deliveries.export'))->assertForbidden();
        $this->actingAs($this->salesPerson)->get(route('reports.export', ['report' => 'sales']))->assertForbidden();
    }
}
