<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'images.array' => 'Les photos doivent être envoyées sous forme de liste.',
            'images.max' => 'Vous pouvez ajouter au maximum 5 photos.',
            'images.*.file' => 'Chaque photo doit être un fichier valide.',
            'images.*.mimes' => 'Chaque photo doit être au format JPG, PNG, WEBP ou AVIF.',
            'images.*.mimetypes' => 'Chaque fichier doit être une image JPG, PNG, WEBP ou AVIF valide.',
        ];
    }
}
