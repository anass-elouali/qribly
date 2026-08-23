<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->unsignedSmallInteger('service_duration_minutes')->nullable();
        });

        DB::table('offers')
            ->where('type', 'service')
            ->update(['service_duration_minutes' => 60]);

        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_minutes')->default(60);
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('service_duration_minutes');
        });
    }
};
