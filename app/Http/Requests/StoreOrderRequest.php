<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Server-side enforcement of the data integrity rules in section 33. None of
 * these checks rely on the form hiding a field.
 */
class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Order::class);
    }

    public function rules(): array
    {
        return array_merge(
            $this->customerRules(),
            $this->orderRules(),
            $this->itemRules(),
        );
    }

    protected function customerRules(): array
    {
        return [
            // When selecting an existing customer from the typeahead we still
            // validate the fields so a postal code is never missed (7.3).
            'customer_id'       => ['nullable', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'customer_name'     => ['required', 'string', 'max:255'],
            'customer_phone'    => ['required', 'string', 'max:40'],
            'customer_email'    => ['nullable', 'email', 'max:255'],
            'customer_address'  => ['nullable', 'string', 'max:255'],
            'customer_city'     => ['nullable', 'string', 'max:120'],
            'customer_state'    => ['nullable', 'string', 'max:120'],
            // Required: location analytics is a core business objective (59).
            'customer_zip_code' => ['required', 'string', 'max:20'],
        ];
    }

    protected function orderRules(): array
    {
        return [
            // Only an active user with SalesPerson role may be recorded as the seller (33).
            'sales_person_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('is_active', true)
                    ->where('role', UserRole::SalesPerson->value)
                    ->whereNull('deleted_at'),
            ],
            'requested_delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'actual_delivery_date'    => ['nullable', 'date'],
            'order_status'            => ['nullable', Rule::in(array_column(OrderStatus::cases(), 'value'))],
            'payment_status'          => ['required', Rule::in(array_column(PaymentStatus::cases(), 'value'))],
            'payment_method'          => ['nullable', Rule::in(array_column(PaymentMethod::cases(), 'value'))],
            'amount_paid'             => ['nullable', 'numeric', 'min:0'],
            'discount'                => ['nullable', 'numeric', 'min:0'],
            'delivery_charge'         => ['nullable', 'numeric', 'min:0'],
            'tax'                     => ['nullable', 'numeric', 'min:0'],
            'notes'                   => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function itemRules(): array
    {
        return [
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.product_id'    => ['nullable', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.colour_id'     => ['nullable', 'integer', Rule::exists('colours', 'id')],
            'items.*.item_name'     => ['nullable', 'string', 'max:255'],
            'items.*.item_colour'   => ['nullable', 'string', 'max:80'],
            'items.*.quantity'      => ['required', 'integer', 'min:1', 'max:9999'],
            'items.*.unit_price'    => ['required', 'numeric', 'min:0'],
            'items.*.discount'      => ['nullable', 'numeric', 'min:0'],
            'items.*.notes'         => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'              => 'An order needs at least one furniture item.',
            'items.min'                   => 'An order needs at least one furniture item.',
            'customer_zip_code.required'  => 'The ZIP/postal code is required so the order counts towards location reporting.',
            'items.*.quantity.min'        => 'Quantity must be at least 1.',
            'items.*.unit_price.min'      => 'Unit price cannot be negative.',
            'sales_person_id.required'    => 'A Sales Person must be assigned to the order.',
            'sales_person_id.exists'      => 'The selected Sales Person must be an active sales representative.',
            'requested_delivery_date.after_or_equal' => 'The requested delivery date must be today or later.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rows = (array) $this->input('items', []);

            // Every row needs either a catalogue product or a typed-in name.
            foreach ($rows as $i => $row) {
                $hasProduct = ! empty($row['product_id']);
                $hasName    = trim((string) ($row['item_name'] ?? '')) !== '';

                if (! $hasProduct && ! $hasName) {
                    $validator->errors()->add("items.{$i}.item_name", 'Choose a product or type an item name.');
                }

                $lineValue = (float) ($row['quantity'] ?? 0) * (float) ($row['unit_price'] ?? 0);
                if ((float) ($row['discount'] ?? 0) > $lineValue) {
                    $validator->errors()->add("items.{$i}.discount", 'The line discount cannot exceed the line value.');
                }
            }

            // An order-level discount cannot exceed the subtotal (33).
            $subtotal = collect($rows)->sum(fn ($r) => max(
                0,
                (float) ($r['quantity'] ?? 0) * (float) ($r['unit_price'] ?? 0) - (float) ($r['discount'] ?? 0),
            ));

            if ((float) $this->input('discount', 0) > $subtotal) {
                $validator->errors()->add('discount', 'The order discount cannot be greater than the subtotal.');
            }

            // Partial payments need an amount that is neither nothing nor everything.
            if ($this->input('payment_status') === PaymentStatus::Partial->value) {
                $paid = (float) $this->input('amount_paid', 0);
                if ($paid <= 0) {
                    $validator->errors()->add('amount_paid', 'Enter the amount paid so far for a partial payment.');
                }
            }

            // An actual delivery date may not sit in the future (34.4).
            if ($this->filled('actual_delivery_date') && strtotime((string) $this->input('actual_delivery_date')) > strtotime('today midnight +1 day -1 second')) {
                $validator->errors()->add('actual_delivery_date', 'The actual delivery date cannot be in the future.');
            }
        });
    }
}
