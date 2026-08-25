<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('service_request_id')
                ->nullable()
                ->after('offer_id')
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('service_request_proposal_id')
                ->nullable()
                ->after('service_request_id')
                ->constrained()
                ->nullOnDelete();
            $table->decimal('agreed_price', 10, 2)
                ->nullable()
                ->after('duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_request_proposal_id');
            $table->dropConstrainedForeignId('service_request_id');
            $table->dropColumn('agreed_price');
        });
    }
};
