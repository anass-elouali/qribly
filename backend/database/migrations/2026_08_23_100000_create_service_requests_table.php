<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->text('raw_text');
            $table->text('summary');
            $table->string('city', 100);
            $table->dateTime('desired_start_at');
            $table->dateTime('desired_end_at');
            $table->decimal('budget_max', 10, 2)->nullable();
            $table->boolean('at_home')->default(true);
            $table->enum('status', [
                'open',
                'fulfilled',
                'cancelled',
            ])->default('open');
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['category_id', 'city', 'status']);
        });

        DB::statement('
            ALTER TABLE service_requests
            ADD COLUMN location geography(Point, 4326)
        ');

        DB::statement('
            CREATE INDEX service_requests_location_gist
            ON service_requests USING GIST (location)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
