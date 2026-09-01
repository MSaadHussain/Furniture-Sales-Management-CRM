<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-products');
    }

    public function rules(): array
    {
        $id = $this->route('category')?->id;

        return [
            'name'        => ['required', 'string', 'max:120', Rule::unique('categories', 'name')->ignore($id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['nullable', 'boolean'],
            'sort_order'  => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
