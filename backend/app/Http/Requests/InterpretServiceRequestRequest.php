<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InterpretServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'raw_text' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'raw_text.required' => 'Décris ton besoin.',
            'raw_text.min' => 'Décris ton besoin avec un peu plus de précision.',
            'raw_text.max' => 'Ta demande ne doit pas dépasser 2 000 caractères.',
        ];
    }
}
