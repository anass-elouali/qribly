<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOfferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => 'required|exists:categories,id',

            'title' => 'required|string|max:255',

            'description' => 'required|string',

            'type' => 'required|in:product,service',

            'service_duration_minutes' => 'nullable|required_if:type,service|integer|min:15|max:480',

            'price' => 'required|numeric|min:0',

            'is_negotiable' => 'required|boolean',

            'status' => 'required|in:active,reserved,sold,inactive',

            'city' => ['nullable', 'string', 'max:100'],

            'location' => ['sometimes', 'array'],
            'location.latitude' => ['required_with:location', 'numeric', 'between:-90,90'],
            'location.longitude' => ['required_with:location', 'numeric', 'between:-180,180'],
        ];
    }
}
