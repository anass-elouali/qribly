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

            'price' => 'required|numeric|min:0',

            'is_negotiable' => 'required|boolean',

            'status' => 'required|in:active,reserved,sold,inactive',
            
            'location' => ['required', 'array'],
            'location.latitude' => ['required', 'numeric', 'between:-90,90'],
            'location.longitude' => ['required', 'numeric', 'between:-180,180'],

            'images' => [
                'nullable',
                'array',
                'max:5',
            ],
            
            'images.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp,avif',
                // 'max:5120',
            ],
        ];
    }
}
