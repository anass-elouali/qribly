<?php

use App\Support\MoroccanCities;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('city', 100)->nullable()->index()->after('location');
        });

        DB::table('offers')
            ->whereNotNull('location')
            ->select('id')
            ->selectRaw('ST_Y(location::geometry) AS latitude')
            ->selectRaw('ST_X(location::geometry) AS longitude')
            ->orderBy('id')
            ->each(function ($offer) {
                DB::table('offers')
                    ->where('id', $offer->id)
                    ->update([
                        'city' => MoroccanCities::nearest(
                            (float) $offer->latitude,
                            (float) $offer->longitude,
                        ),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('city');
        });
    }
};
