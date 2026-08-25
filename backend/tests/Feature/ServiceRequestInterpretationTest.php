<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ServiceRequestInterpretationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-08-24 06:00:00', 'UTC'),
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_authentication_is_required_to_interpret_a_request(): void
    {
        Http::fake();

        $this
            ->postJson('/api/assistant/interpret-service-request', [
                'raw_text' => 'Je cherche un plombier à Rabat demain.',
            ])
            ->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_customer_can_interpret_a_service_request(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Services à domicile']);
        Http::fake([
            '*' => Http::response($this->validInterpretation($category), 200),
        ]);

        $this
            ->actingAs($user)
            ->postJson('/api/assistant/interpret-service-request', [
                'raw_text' => 'Mon robinet fuit à Rabat demain, budget 300 DH.',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.category_id', $category->id)
            ->assertJsonPath('data.category_name', 'Services à domicile')
            ->assertJsonPath('data.city', 'Rabat')
            ->assertJsonPath('meta.interpreter', 'local');

        Http::assertSent(function (Request $request) use ($category, $user): bool {
            return $request->url() === config('services.ai.url').'/interpret-service-request'
                && $request['raw_text'] === 'Mon robinet fuit à Rabat demain, budget 300 DH.'
                && $request['categories'] === [[
                    'id' => $category->id,
                    'name' => 'Services à domicile',
                ]]
                && in_array('Fès', $request['cities'], true)
                && is_string($request['current_time'])
                && $request['safety_identifier'] === hash('sha256', "qribly:user:{$user->id}");
        });
    }

    public function test_invalid_input_is_rejected_before_calling_the_ai_service(): void
    {
        Http::fake();

        $this
            ->actingAs(User::factory()->create())
            ->postJson('/api/assistant/interpret-service-request', [
                'raw_text' => 'Court',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('raw_text');

        Http::assertNothingSent();
    }

    public function test_ai_service_failure_returns_an_explicit_manual_fallback(): void
    {
        Category::create(['name' => 'Services à domicile']);
        Http::fake(['*' => Http::response(['detail' => 'unavailable'], 503)]);

        $this
            ->actingAs(User::factory()->create())
            ->postJson('/api/assistant/interpret-service-request', [
                'raw_text' => 'Je cherche un plombier à Rabat demain.',
            ])
            ->assertServiceUnavailable()
            ->assertJsonPath('fallback', true);
    }

    public function test_incomplete_interpretation_returns_only_two_questions(): void
    {
        Category::create(['name' => 'Services à domicile']);
        Http::fake(['*' => Http::response([
            'data' => [
                'summary' => 'Je cherche une aide rapidement à Rabat.',
                'category_id' => null,
                'category_name' => null,
                'city' => 'Rabat',
                'desired_start_at' => null,
                'desired_end_at' => null,
                'budget_max' => null,
                'at_home' => null,
                'missing_fields' => ['category_id', 'desired_period', 'at_home'],
                'questions' => [
                    'Quel type de service recherches-tu ?',
                    'Pour quelle date souhaites-tu ce service ?',
                ],
            ],
            'meta' => ['interpreter' => 'local'],
        ], 200)]);

        $this
            ->actingAs(User::factory()->create())
            ->postJson('/api/assistant/interpret-service-request', [
                'raw_text' => 'Je cherche une aide rapidement à Rabat.',
            ])
            ->assertSuccessful()
            ->assertJsonCount(2, 'data.questions')
            ->assertJsonPath('data.missing_fields.0', 'category_id')
            ->assertJsonPath('data.missing_fields.1', 'desired_period');
    }

    public function test_untrusted_category_from_ai_service_is_rejected(): void
    {
        $category = Category::create(['name' => 'Services à domicile']);
        $payload = $this->validInterpretation($category);
        $payload['data']['category_id'] = 999;
        $payload['data']['category_name'] = 'Catégorie inventée';
        Http::fake(['*' => Http::response($payload, 200)]);

        $this
            ->actingAs(User::factory()->create())
            ->postJson('/api/assistant/interpret-service-request', [
                'raw_text' => 'Je cherche un plombier à Rabat demain.',
            ])
            ->assertServiceUnavailable()
            ->assertJsonPath('fallback', true);
    }

    public function test_interpretation_endpoint_is_rate_limited(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Services à domicile']);
        Http::fake(['*' => Http::response($this->validInterpretation($category), 200)]);
        $payload = ['raw_text' => 'Je cherche un plombier à Rabat demain.'];

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this
                ->actingAs($user)
                ->postJson('/api/assistant/interpret-service-request', $payload)
                ->assertSuccessful();
        }

        $this
            ->actingAs($user)
            ->postJson('/api/assistant/interpret-service-request', $payload)
            ->assertTooManyRequests();
    }

    /** @return array<string, mixed> */
    private function validInterpretation(Category $category): array
    {
        return [
            'data' => [
                'summary' => 'Réparer une fuite à domicile à Rabat.',
                'category_id' => $category->id,
                'category_name' => $category->name,
                'city' => 'Rabat',
                'desired_start_at' => '2026-08-25T08:00:00Z',
                'desired_end_at' => '2026-08-25T18:00:00Z',
                'budget_max' => 300,
                'at_home' => true,
                'missing_fields' => [],
                'questions' => [],
            ],
            'meta' => ['interpreter' => 'local'],
        ];
    }
}
