<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Twelve months of sample orders so every dashboard, trend chart and report has
 * something meaningful to show, including deliveries scheduled for today.
 */
class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $customers    = Customer::all();
        $products     = Product::with('colours')->get();
        $salesPersons = User::whereIn('role', [UserRole::SalesPerson->value, UserRole::Manager->value])->get();
        $admin        = User::where('role', UserRole::Admin->value)->first();

        if ($customers->isEmpty() || $products->isEmpty()) {
            $this->command?->warn('Seed customers and products before orders.');

            return;
        }

        $sequence = 0;

        // Historical orders across the last 12 months.
        for ($daysAgo = 365; $daysAgo >= 0; $daysAgo--) {
            $date = Carbon::today()->subDays($daysAgo);

            // More orders in recent months, and a weekend dip, so the trend
            // charts and growth comparisons show a realistic shape.
            $base   = $daysAgo > 180 ? 1 : 2;
            $bonus  = $date->isWeekend() ? 0 : 1;
            $count  = max(0, $base + $bonus - rand(0, 2));

            for ($n = 0; $n < $count; $n++) {
                $this->makeOrder($date, ++$sequence, $customers, $products, $salesPersons, $admin);
            }
        }

        // A guaranteed block of deliveries scheduled for today so the daily
        // operations panel is never empty on a fresh install.
        for ($n = 0; $n < 8; $n++) {
            $this->makeOrder(
                Carbon::today()->subDays(rand(3, 20)),
                ++$sequence,
                $customers,
                $products,
                $salesPersons,
                $admin,
                forceDeliveryToday: true,
            );
        }
    }

    private function makeOrder(
        Carbon $createdOn,
        int $sequence,
        $customers,
        $products,
        $salesPersons,
        ?User $admin,
        bool $forceDeliveryToday = false,
    ): void {
        $customer = $customers->random();

        $createdAt = $createdOn->copy()->setTime(rand(9, 19), rand(0, 59));
        $requested = $forceDeliveryToday
            ? Carbon::today()
            : $createdOn->copy()->addDays(rand(3, 21));

        $order = new Order([
            'customer_id'             => $customer->id,
            'sales_person_id'         => $salesPersons->isNotEmpty() ? $salesPersons->random()->id : null,
            'requested_delivery_date' => $requested,
            'zip_code'                => $customer->zip_code,
            'created_by'              => $admin?->id,
            'updated_by'              => $admin?->id,
        ]);

        $order->order_number     = sprintf('SALE-%s-%06d', $createdAt->format('Y'), $sequence);
        $order->order_created_at = $createdAt;
        $order->order_status     = OrderStatus::New;
        $order->payment_status   = PaymentStatus::Pending;
        $order->save();

        // 1 to 3 line items per order.
        $subtotal = 0;
        foreach ($products->random(rand(1, 3)) as $product) {
            $colour   = $product->colours->isNotEmpty() ? $product->colours->random() : null;
            $quantity = in_array($product->name, ['Dining Chair', 'Bar Stool'], true) ? rand(2, 6) : rand(1, 2);
            $price    = (float) $product->default_price;
            $discount = rand(0, 4) === 0 ? round($price * $quantity * 0.05, 2) : 0;
            $lineTotal = OrderItem::computeLineTotal($quantity, $price, $discount);

            OrderItem::create([
                'order_id'               => $order->id,
                'product_id'             => $product->id,
                'colour_id'              => $colour?->id,
                'item_name_snapshot'     => $product->name,
                'item_colour'            => $colour?->name,
                'category_name_snapshot' => $product->category?->name,
                'quantity'               => $quantity,
                'unit_price'             => $price,
                'discount'               => $discount,
                'line_total'             => $lineTotal,
            ]);

            $subtotal += $lineTotal;
        }

        $deliveryCharge = rand(0, 1) ? 2500 : 0;
        $grandTotal     = round($subtotal + $deliveryCharge, 2);

        [$status, $actualDelivery, $paymentStatus] = $this->resolveState($requested, $forceDeliveryToday);

        $amountPaid = match ($paymentStatus) {
            PaymentStatus::Paid    => $grandTotal,
            PaymentStatus::Partial => round($grandTotal * 0.4, 2),
            default                => 0.0,
        };

        $order->forceFill([
            'subtotal'             => $subtotal,
            'discount'             => 0,
            'delivery_charge'      => $deliveryCharge,
            'tax'                  => 0,
            'grand_total'          => $grandTotal,
            'order_status'         => $status,
            'payment_status'       => $paymentStatus,
            'payment_method'       => PaymentMethod::cases()[array_rand(PaymentMethod::cases())],
            'amount_paid'          => $amountPaid,
            'balance_due'          => max(0, $grandTotal - $amountPaid),
            'actual_delivery_date' => $actualDelivery,
        ])->save();
    }

    /**
     * Picks a plausible status for the order given how its requested delivery
     * date compares with today, including a small share of late deliveries so
     * the on-time rate is not a flat 100 percent.
     *
     * @return array{0:OrderStatus,1:?Carbon,2:PaymentStatus}
     */
    private function resolveState(Carbon $requested, bool $forceDeliveryToday): array
    {
        if ($forceDeliveryToday) {
            $status = [OrderStatus::ReadyForDelivery, OrderStatus::Processing, OrderStatus::OutForDelivery, OrderStatus::Delayed][rand(0, 3)];

            return [$status, null, rand(0, 1) ? PaymentStatus::Partial : PaymentStatus::Pending];
        }

        // Future delivery: still working through the pipeline.
        if ($requested->isAfter(Carbon::today())) {
            $status = [OrderStatus::New, OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::ReadyForDelivery][rand(0, 3)];

            return [$status, null, rand(0, 2) === 0 ? PaymentStatus::Partial : PaymentStatus::Pending];
        }

        // Past delivery date: mostly delivered, a few cancelled or still open.
        $roll = rand(1, 100);

        if ($roll <= 6) {
            return [OrderStatus::Cancelled, null, PaymentStatus::Refunded];
        }

        if ($roll <= 12) {
            return [OrderStatus::Delayed, null, PaymentStatus::Partial];
        }

        // 1 in 8 delivered orders runs late.
        $daysLate = rand(1, 8) === 1 ? rand(1, 5) : 0;

        return [OrderStatus::Delivered, $requested->copy()->addDays($daysLate), PaymentStatus::Paid];
    }
}
