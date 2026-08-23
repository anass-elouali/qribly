<?php

namespace App\Http\Controllers;

use App\Http\Requests\OfferAvailabilityRequest;
use App\Http\Requests\UpdateProviderAvailabilityRequest;
use App\Models\Offer;
use App\Models\User;
use App\Services\OfferAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProviderAvailabilityController extends Controller
{
    public function offer(
        OfferAvailabilityRequest $request,
        Offer $offer,
        OfferAvailabilityService $availabilityService,
    ) {
        if ($offer->type !== 'service') {
            return response()->json([
                'message' => "Cette annonce n'est pas un service réservable.",
            ], 422);
        }

        $from = CarbonImmutable::createFromFormat(
            'Y-m-d',
            $request->validated('from') ?? CarbonImmutable::now(OfferAvailabilityService::TIMEZONE)->toDateString(),
            OfferAvailabilityService::TIMEZONE,
        )->startOfDay();

        return response()->json(
            $availabilityService->slots(
                $offer,
                $from,
                $request->integer('days', 14),
            ),
        );
    }

    public function show(Request $request)
    {
        $days = $request->user()
            ->providerAvailabilities()
            ->orderBy('day_of_week')
            ->get(['day_of_week', 'start_time', 'end_time']);

        return response()->json([
            'configured' => $days->isNotEmpty(),
            'timezone' => OfferAvailabilityService::TIMEZONE,
            'days' => $days,
        ]);
    }

    public function update(UpdateProviderAvailabilityRequest $request)
    {
        $enabledDays = collect($request->validated('days'))
            ->filter(fn (array $day) => $day['enabled'])
            ->map(fn (array $day) => [
                'day_of_week' => $day['day_of_week'],
                'start_time' => $day['start_time'],
                'end_time' => $day['end_time'],
            ])
            ->values();

        DB::transaction(function () use ($request, $enabledDays) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);

            $user->providerAvailabilities()->delete();
            $user->providerAvailabilities()->createMany($enabledDays->all());
        });

        return $this->show($request);
    }
}
