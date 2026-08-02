<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            
            $table->id();

            // Relationships
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->foreignId('category_id')->constrained()->restrictOnDelete();

            // Basic information
            $table->string('title');
            $table->text('description');

            // Offer type
            $table->enum('type', ['product', 'service']);

            // Price
            $table->decimal('price', 10, 2);
            $table->boolean('is_negotiable')->default(false);

            // Status
            $table->enum('status', [
                'active',
                'reserved',
                'sold',
                'inactive'
            ])->default('active');

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
