<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_request_proposals', function (Blueprint $table) {
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
            $table->decimal('proposed_price', 10, 2);
            $table->dateTime('scheduled_at');
            $table->text('message')->nullable();
            $table->enum('status', [
                'pending',
                'accepted',
                'declined',
                'withdrawn',
            ])->default('pending');
            $table->timestamps();

            $table->unique(['service_request_id', 'provider_id']);
            $table->index(['service_request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_proposals');
    }
};
