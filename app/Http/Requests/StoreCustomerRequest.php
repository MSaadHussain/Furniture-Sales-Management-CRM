<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer
            ? $this->user()->can('update', $customer)
            : $this->user()->can('create', Customer::class);
    }

    public function rules(): array
    {
        $id = $this->route('customer')?->id;

        return [
            'name'     => ['required', 'string', 'max:255'],
            'phone'    => ['required', 'string', 'max:40'],
            'phone_alt' => ['nullable', 'string', 'max:40'],
            'email'    => ['nullable', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($id)->whereNull('deleted_at')],
            'address'  => ['nullable', 'string', 'max:255'],
            'city'     => ['nullable', 'string', 'max:120'],
            'state'    => ['nullable', 'string', 'max:120'],
            'zip_code' => ['required', 'string', 'max:20'],
            'notes'    => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'zip_code.required' => 'The ZIP/postal code is required for location reporting.',
            'email.unique'      => 'Another customer already uses this email address.',
        ];
    }
}
