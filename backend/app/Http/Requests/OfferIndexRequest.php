<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OfferIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => [
                'nullable',
                'integer',
                'exists:categories,id',
            ],

            'q' => [
                'nullable',
                'string',
                'max:255',
            ],

            'type' => [
                'nullable',
                Rule::in(['product', 'service']),
            ],

            'status' => [
                'nullable',
                Rule::in([
                    'active',
                    'reserved',
                    'sold',
                    'inactive',
                ]),
            ],

            'min_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'max_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $minPrice = $this->input('min_price');
            $maxPrice = $this->input('max_price');

            if (
                $minPrice !== null &&
                $maxPrice !== null &&
                $minPrice > $maxPrice
            ) {
                $validator->errors()->add(
                    'max_price',
                    'The max price must be greater than or equal to the min price.'
                );
            }
        });
    }
}