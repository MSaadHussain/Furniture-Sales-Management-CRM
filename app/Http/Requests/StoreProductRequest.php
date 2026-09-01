<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product
            ? $this->user()->can('update', $product)
            : $this->user()->can('create', Product::class);
    }

    public function rules(): array
    {
        $id = $this->route('product')?->id;

        return [
            'product_code' => ['nullable', 'string', 'max:60', Rule::unique('products', 'product_code')->ignore($id)],
            'name'         => ['required', 'string', 'max:255'],
            'category_id'  => ['required', 'integer', Rule::exists('categories', 'id')],
            'default_price'=> ['required', 'numeric', 'min:0'],
            'description'  => ['nullable', 'string', 'max:2000'],
            'is_active'    => ['nullable', 'boolean'],
            'colours'      => ['nullable', 'array'],
            'colours.*'    => ['integer', Rule::exists('colours', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'default_price.min' => 'Prices cannot be negative.',
        ];
    }
}
