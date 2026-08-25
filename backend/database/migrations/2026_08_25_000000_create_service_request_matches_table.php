<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_request_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('provider_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('offer_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->decimal('relevance_score', 5, 4)->nullable();
            $table->timestamps();

            $table->unique(['service_request_id', 'provider_id']);
            $table->index(['provider_id', 'relevance_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_matches');
    }
};
