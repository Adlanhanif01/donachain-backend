<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => ['required', Rule::in(['donor', 'creator'])],
            'phone' => ['required_if:role,creator', 'nullable', 'string', 'max:40'],
            'address' => ['required_if:role,creator', 'nullable', 'string', 'max:2000'],
            'organization' => ['nullable', 'string', 'max:255'],
            'id_card' => [
                'required_if:role,creator',
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
        ];
    }
}
