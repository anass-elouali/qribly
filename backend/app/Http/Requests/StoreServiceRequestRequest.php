<?php

namespace App\Http\Requests;

use App\Support\MoroccanCities;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'raw_text' => ['required', 'string', 'min:10', 'max:2000'],
            'summary' => ['required', 'string', 'min:10', 'max:1000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'city' => [
                'required',
                'string',
                'max:100',
                Rule::in(array_column(MoroccanCities::ALL, 'name')),
            ],
            'desired_start_at' => ['required', 'date', 'after:now'],
            'desired_end_at' => ['required', 'date', 'after:desired_start_at'],
            'budget_max' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'at_home' => ['required', 'boolean'],
            'location' => ['nullable', 'array'],
            'location.latitude' => ['required_with:location', 'numeric', 'between:-90,90'],
            'location.longitude' => ['required_with:location', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'raw_text.required' => 'Décris ton besoin.',
            'raw_text.min' => 'Décris ton besoin avec un peu plus de précision.',
            'summary.required' => 'Le résumé de la demande est obligatoire.',
            'category_id.required' => 'Choisis une catégorie.',
            'category_id.exists' => 'La catégorie sélectionnée est invalide.',
            'city.required' => 'Choisis une ville.',
            'city.in' => "La ville sélectionnée n'est pas prise en charge.",
            'desired_start_at.after' => 'Le début de la période doit être à venir.',
            'desired_end_at.after' => 'La fin de la période doit être postérieure au début.',
            'budget_max.min' => 'Le budget ne peut pas être négatif.',
            'at_home.required' => 'Précise si le service doit être réalisé à domicile.',
        ];
    }
}
