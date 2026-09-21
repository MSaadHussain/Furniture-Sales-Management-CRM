<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Sheets\SheetsWebAppClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Google Sheets mirror: dirty tracking, upsert payloads, deletions and the
 * request signing. Nothing here talks to Google; the Web App is faked.
 */
class GoogleSheetSyncTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://script.google.com/macros/s/fake/exec';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sheets.enabled' => true,
            'sheets.url'     => self::URL,
            'sheets.secret'  => 'test-secret',
        ]);
    }

    /**
     * Http::fake() merges stubs rather than replacing them and the first match
     * wins, so a test that needs a failure must be the one to stub first.
     */
    private function fakeSheet(array $body = ['ok' => true, 'written' => []]): void
    {
        Http::fake([self::URL => Http::response($body)]);
    }

    /** The decoded `payload` of the nth request the command made. */
    private function sentPayload(int $index = 0): array
    {
        $request = Http::recorded()[$index][0];

        return json_decode(json_decode($request->body(), true)['payload'], true);
    }

    private function orderWithItems(int $items = 1): Order
    {
        $order = Order::factory()->create(['customer_id' => Customer::factory()]);

        for ($i = 0; $i < $items; $i++) {
            OrderItem::create([
                'order_id'           => $order->id,
                'item_name_snapshot' => "Sofa {$i}",
                'quantity'           => 1,
                'unit_price'         => 1000,
                'discount'           => 0,
                'line_total'         => 1000,
            ]);
        }

        return $order->fresh();
    }

    public function test_nothing_is_sent_while_the_sync_is_unconfigured(): void
    {
        config(['sheets.enabled' => false]);
        $this->fakeSheet();
        $this->orderWithItems();

        $this->artisan('sheets:sync')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_rows_that_predate_the_feature_are_backfilled_on_the_first_run(): void
    {
        $this->fakeSheet();
        $order = $this->orderWithItems();
        // Simulate history: a row that was never touched by an observer.
        DB::table('orders')->update(['sheet_synced_at' => null]);

        $this->artisan('sheets:sync')->assertSuccessful();

        $rows = $this->sentPayload()['sheets']['Orders']['rows'];

        $this->assertCount(1, $rows);
        $this->assertSame($order->id, $rows[0][0]);
        $this->assertSame($order->order_number, $rows[0][1]);
        $this->assertNotNull($order->fresh()->sheet_synced_at);
    }

    public function test_a_synced_row_is_not_sent_again(): void
    {
        $this->fakeSheet();
        $this->orderWithItems();
        $this->artisan('sheets:sync')->assertSuccessful();

        Http::fake([self::URL => Http::response(['ok' => true])]);
        $this->artisan('sheets:sync')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_editing_an_order_makes_it_dirty_again(): void
    {
        $this->fakeSheet();
        $order = $this->orderWithItems();
        $this->artisan('sheets:sync')->assertSuccessful();

        $order->update(['notes' => 'Leave at the back gate']);

        $this->assertNull($order->fresh()->sheet_synced_at);
    }

    public function test_changing_a_line_item_makes_the_parent_order_dirty(): void
    {
        $this->fakeSheet();
        $order = $this->orderWithItems();
        $this->artisan('sheets:sync')->assertSuccessful();

        $order->items()->first()->update(['quantity' => 4]);

        $this->assertNull($order->fresh()->sheet_synced_at);
    }

    public function test_a_deleted_order_is_queued_for_removal_and_sent(): void
    {
        $this->fakeSheet();
        $order = $this->orderWithItems();
        $this->artisan('sheets:sync')->assertSuccessful();

        $order->delete();

        $this->assertDatabaseHas('sheet_sync_deletions', [
            'entity'    => 'orders',
            'entity_id' => $order->id,
        ]);

        Http::fake([self::URL => Http::response(['ok' => true])]);
        $this->artisan('sheets:sync')->assertSuccessful();

        $this->assertSame([$order->id], $this->sentPayload()['sheets']['Orders']['deletes']);
        $this->assertDatabaseCount('sheet_sync_deletions', 0);
    }

    public function test_customers_and_products_travel_in_their_own_tabs(): void
    {
        $this->fakeSheet();
        Customer::factory()->create(['name' => 'Sheet Customer']);
        Product::factory()->create(['name' => 'Sheet Product']);

        $this->artisan('sheets:sync')->assertSuccessful();

        $sheets = $this->sentPayload()['sheets'];

        $this->assertArrayHasKey('Customers', $sheets);
        $this->assertArrayHasKey('Products', $sheets);
        $this->assertContains('Sheet Customer', array_column($sheets['Customers']['rows'], 1));
        $this->assertContains('Sheet Product', array_column($sheets['Products']['rows'], 2));
    }

    public function test_the_id_is_always_the_first_column(): void
    {
        $service = app(\App\Services\Sheets\SheetSyncService::class);

        foreach (['orders', 'customers', 'products'] as $entity) {
            $this->assertSame('ID', $service->headers($entity)[0]);
        }
    }

    public function test_every_request_is_signed_and_timestamped(): void
    {
        $this->fakeSheet();
        $this->orderWithItems();

        $this->artisan('sheets:sync')->assertSuccessful();

        Http::assertSent(function (Request $request) {
            $envelope = json_decode($request->body(), true);

            $expected = app(SheetsWebAppClient::class)->sign($envelope['ts'], $envelope['payload']);

            return hash_equals($expected, $envelope['sig'])
                && abs(time() - (int) $envelope['ts']) < 60;
        });
    }

    public function test_rows_stay_dirty_when_the_web_app_rejects_the_write(): void
    {
        $this->fakeSheet(['ok' => false, 'error' => 'Bad signature']);

        $order = $this->orderWithItems();

        $this->artisan('sheets:sync')->assertFailed();

        $this->assertNull($order->fresh()->sheet_synced_at);
    }

    public function test_a_full_repush_reflags_every_row(): void
    {
        $this->fakeSheet();
        $this->orderWithItems();
        Customer::factory()->create();
        $this->artisan('sheets:sync')->assertSuccessful();

        Http::fake([self::URL => Http::response(['ok' => true])]);
        $this->artisan('sheets:sync', ['--all' => true])->assertSuccessful();

        $this->assertNotEmpty($this->sentPayload()['sheets']['Orders']['rows']);
    }
}
