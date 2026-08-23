<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OfferAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'min:1', 'max:31'],
        ];
    }
}
