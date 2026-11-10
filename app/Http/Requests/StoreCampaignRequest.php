<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:10000'],
            'target_amount' => ['required', 'numeric', 'gt:0', 'max:1000000000000'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'milestones' => ['required', 'array', 'min:1', 'max:20'],
            'milestones.*.title' => ['required', 'string', 'max:255'],
            'milestones.*.description' => ['required', 'string', 'max:5000'],
            'milestones.*.amount_percentage' => ['required', 'integer', 'between:1,100'],
        ];
    }
}
