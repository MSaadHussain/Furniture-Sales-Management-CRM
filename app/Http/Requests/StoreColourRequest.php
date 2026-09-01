<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreColourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-products');
    }

    public function rules(): array
    {
        $id = $this->route('colour')?->id;

        return [
            'name'       => ['required', 'string', 'max:80', Rule::unique('colours', 'name')->ignore($id)],
            'hex'        => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active'  => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return ['hex.regex' => 'Use a 6-digit hex colour such as #6B7280.'];
    }
}
