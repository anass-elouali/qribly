<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProviderAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'days' => ['required', 'array', 'min:1', 'max:7'],
            'days.*.day_of_week' => ['required', 'integer', 'between:0,6', 'distinct'],
            'days.*.enabled' => ['required', 'boolean'],
            'days.*.start_time' => ['nullable', 'date_format:H:i'],
            'days.*.end_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $enabledDays = collect($this->input('days', []))
                    ->filter(fn (array $day) => $day['enabled'] ?? false);

                if ($enabledDays->isEmpty()) {
                    $validator->errors()->add(
                        'days',
                        'Active au moins un jour disponible.',
                    );
                }

                foreach ($this->input('days', []) as $index => $day) {
                    if (! ($day['enabled'] ?? false)) {
                        continue;
                    }

                    $start = $day['start_time'] ?? null;
                    $end = $day['end_time'] ?? null;

                    if (! $start) {
                        $validator->errors()->add(
                            "days.{$index}.start_time",
                            "Indique l'heure de début.",
                        );
                    }

                    if (! $end) {
                        $validator->errors()->add(
                            "days.{$index}.end_time",
                            "Indique l'heure de fin.",
                        );
                    }

                    if ($start && $end && $end <= $start) {
                        $validator->errors()->add(
                            "days.{$index}.end_time",
                            "L'heure de fin doit être après l'heure de début.",
                        );
                    }
                }
            },
        ];
    }
}
