<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOfferRequest extends FormRequest
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

            'location' => ['required', 'array'],
            'location.latitude' => ['required', 'numeric', 'between:-90,90'],
            'location.longitude' => ['required', 'numeric', 'between:-180,180'],

            'images' => [
                'nullable',
                'array',
                'max:5',
            ],

            'images.*' => [
                'bail',
                'file',
                'mimes:jpg,jpeg,png,webp,avif',
                'mimetypes:image/jpeg,image/png,image/webp,image/avif',
            ],
        ];
    }

    /**
     * Get the validation error messages for the request.
     *
     * Laravel's `image` rule does not include AVIF in the framework version
     * used by the project, even though the frontend explicitly supports it.
     */
    public function messages(): array
    {
        return [
            'at_customer_location.required_if' => 'Indique où ce service peut être réalisé.',
            'at_provider_location.required_if' => 'Indique où ce service peut être réalisé.',
            'images.array' => 'Les photos doivent être envoyées sous forme de liste.',
            'images.max' => 'Vous pouvez ajouter au maximum 5 photos.',
            'images.*.file' => 'Chaque photo doit être un fichier valide.',
            'images.*.mimes' => 'Chaque photo doit être au format JPG, PNG, WEBP ou AVIF.',
            'images.*.mimetypes' => 'Chaque fichier doit être une image JPG, PNG, WEBP ou AVIF valide.',
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
