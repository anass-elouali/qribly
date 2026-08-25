<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

            'at_customer_location' => 'nullable|required_if:type,service|boolean',

            'at_provider_location' => 'nullable|required_if:type,service|boolean',

            'price' => 'required|numeric|min:0',

            'is_negotiable' => 'required|boolean',

            'status' => 'required|in:active,reserved,sold,inactive',

            'city' => ['nullable', 'string', 'max:100'],

            'location' => ['sometimes', 'array'],
            'location.latitude' => ['required_with:location', 'numeric', 'between:-90,90'],
            'location.longitude' => ['required_with:location', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'at_customer_location.required_if' => 'Indique où ce service peut être réalisé.',
            'at_provider_location.required_if' => 'Indique où ce service peut être réalisé.',
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (
                    $this->input('type') === 'service'
                    && ! $this->boolean('at_customer_location')
                    && ! $this->boolean('at_provider_location')
                ) {
                    $validator->errors()->add(
                        'at_customer_location',
                        'Choisis au moins un lieu pour réaliser ce service.',
                    );
                }
            },
        ];
    }
}
