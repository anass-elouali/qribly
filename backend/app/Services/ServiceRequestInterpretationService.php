<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use App\Support\MoroccanCities;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class ServiceRequestInterpretationService
{
    /**
     * @return array{data: array<string, mixed>, meta: array{interpreter: string}}|null
     */
    public function interpret(User $user, string $rawText): ?array
    {
        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'name']);
        $cities = array_column(MoroccanCities::ALL, 'name');

        try {
            $response = Http::acceptJson()
                ->timeout(config('services.ai.timeout'))
                ->post(config('services.ai.url').'/interpret-service-request', [
                    'raw_text' => $rawText,
                    'categories' => $categories
                        ->map(fn (Category $category) => [
                            'id' => $category->id,
                            'name' => $category->name,
                        ])
                        ->values()
                        ->all(),
                    'cities' => $cities,
                    'current_time' => now()->toIso8601String(),
                    'safety_identifier' => hash('sha256', "qribly:user:{$user->id}"),
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Service-request interpreter is unavailable.', [
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Service-request interpreter returned an error.', [
                'status' => $response->status(),
            ]);

            return null;
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            Log::warning('Service-request interpreter returned invalid JSON.');

            return null;
        }

        $validator = Validator::make($payload, [
            'data' => ['required', 'array'],
            'data.summary' => ['required', 'string', 'min:10', 'max:1000'],
            'data.category_id' => ['present', 'nullable', 'integer', 'min:1'],
            'data.category_name' => ['present', 'nullable', 'string', 'max:100'],
            'data.city' => ['present', 'nullable', 'string', Rule::in($cities)],
            'data.desired_start_at' => ['present', 'nullable', 'date'],
            'data.desired_end_at' => ['present', 'nullable', 'date'],
            'data.budget_max' => ['present', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'data.at_home' => ['present', 'nullable', 'boolean'],
            'data.missing_fields' => ['present', 'array', 'max:4'],
            'data.missing_fields.*' => [
                'required',
                'string',
                'distinct',
                Rule::in(['category_id', 'city', 'desired_period', 'at_home']),
            ],
            'data.questions' => ['present', 'array', 'max:2'],
            'data.questions.*' => ['required', 'string', 'min:5', 'max:200'],
            'meta' => ['required', 'array'],
            'meta.interpreter' => ['required', Rule::in(['local', 'openai'])],
        ]);

        if ($validator->fails()) {
            Log::warning('Service-request interpreter response failed validation.', [
                'errors' => $validator->errors()->keys(),
            ]);

            return null;
        }

        $validated = $validator->validated();
        $data = $validated['data'];

        if (! $this->hasAllowedCategory($categories->all(), $data)) {
            Log::warning('Service-request interpreter returned an unsupported category.');

            return null;
        }

        if (! $this->hasValidPeriod($data)) {
            Log::warning('Service-request interpreter returned an invalid period.');

            return null;
        }

        if (! $this->hasConsistentMissingFields($data)) {
            Log::warning('Service-request interpreter returned inconsistent missing fields.');

            return null;
        }

        return [
            'data' => $data,
            'meta' => ['interpreter' => $validated['meta']['interpreter']],
        ];
    }

    /**
     * @param  array<int, Category>  $categories
     * @param  array<string, mixed>  $data
     */
    private function hasAllowedCategory(array $categories, array $data): bool
    {
        if ($data['category_id'] === null || $data['category_name'] === null) {
            return $data['category_id'] === null && $data['category_name'] === null;
        }

        foreach ($categories as $category) {
            if (
                $category->id === (int) $data['category_id']
                && $category->name === $data['category_name']
            ) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $data */
    private function hasValidPeriod(array $data): bool
    {
        if ($data['desired_start_at'] === null || $data['desired_end_at'] === null) {
            return $data['desired_start_at'] === null && $data['desired_end_at'] === null;
        }

        try {
            $start = CarbonImmutable::parse($data['desired_start_at']);
            $end = CarbonImmutable::parse($data['desired_end_at']);
            $now = CarbonImmutable::instance(now());
        } catch (Throwable) {
            return false;
        }

        return $start->isAfter($now)
            && $end->isAfter($start)
            && $end->lte($now->addDays(31));
    }

    /** @param array<string, mixed> $data */
    private function hasConsistentMissingFields(array $data): bool
    {
        $expected = [];

        if ($data['category_id'] === null) {
            $expected[] = 'category_id';
        }
        if ($data['city'] === null) {
            $expected[] = 'city';
        }
        if ($data['desired_start_at'] === null) {
            $expected[] = 'desired_period';
        }
        if ($data['at_home'] === null) {
            $expected[] = 'at_home';
        }

        return $data['missing_fields'] === $expected
            && count($data['questions']) === min(2, count($expected));
    }
}
