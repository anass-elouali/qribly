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
            $table->boolean('at_customer_location')->default(false);
            $table->boolean('at_provider_location')->default(false);
        });

        DB::table('offers')
            ->where('type', 'service')
            ->update([
                'at_customer_location' => true,
                'at_provider_location' => true,
            ]);
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn([
                'at_customer_location',
                'at_provider_location',
            ]);
        });
    }
};
