<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequestProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'offer_id' => ['required', 'integer', 'exists:offers,id'],
            'proposed_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'offer_id.required' => 'Choisis le service proposé.',
            'offer_id.exists' => "L'annonce sélectionnée est introuvable.",
            'proposed_price.required' => 'Indique le prix proposé.',
            'proposed_price.min' => 'Le prix proposé ne peut pas être négatif.',
            'scheduled_at.required' => 'Choisis un créneau.',
            'scheduled_at.after' => 'Le créneau doit être à venir.',
            'message.max' => 'Le message ne peut pas dépasser 1000 caractères.',
        ];
    }
}
