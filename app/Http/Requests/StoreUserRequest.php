<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-users');
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name'      => ['required', 'string', 'max:120'],
            'email'     => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($userId)->whereNull('deleted_at')],
            'phone'     => ['nullable', 'string', 'max:40'],
            'role'      => ['required', new Enum(UserRole::class)],
            'is_active' => ['nullable', 'boolean'],
            // Password is optional on edit: leaving it blank keeps the current one.
            'password'  => [$userId ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ];
    }
}
