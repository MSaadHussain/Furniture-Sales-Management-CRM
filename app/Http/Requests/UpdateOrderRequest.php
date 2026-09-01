<?php

namespace App\Http\Requests;

/**
 * Same rules as creating an order, except the requested delivery date may be
 * moved to a past date when correcting historical data (requirements 34.2).
 */
class UpdateOrderRequest extends StoreOrderRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('order'));
    }

    protected function orderRules(): array
    {
        $rules = parent::orderRules();
        $rules['requested_delivery_date'] = ['required', 'date'];

        return $rules;
    }
}
