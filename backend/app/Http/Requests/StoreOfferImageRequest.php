<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOfferImageRequest extends FormRequest
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
            'images' => ['required', 'array', 'min:1'],
            'images.*' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,avif',
                'mimetypes:image/jpeg,image/png,image/webp,image/avif',
                'max:5120',
            ],
        ];
    }

    /**
     * Get the validation error messages for the request.
     */
    public function messages(): array
    {
        return [
            'images.required' => 'Ajoutez au moins une photo.',
            'images.array' => 'Les photos doivent être envoyées sous forme de liste.',
            'images.min' => 'Ajoutez au moins une photo.',
            'images.*.required' => 'Chaque photo est obligatoire.',
            'images.*.file' => 'Chaque photo doit être un fichier valide.',
            'images.*.mimes' => 'Chaque photo doit être au format JPG, PNG, WEBP ou AVIF.',
            'images.*.mimetypes' => 'Chaque fichier doit être une image JPG, PNG, WEBP ou AVIF valide.',
            'images.*.max' => 'Chaque photo ne doit pas dépasser 5 Mo.',
        ];
    }
}
