<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['user_one_id', 'user_two_id']);
            $table->foreignId('service_request_proposal_id')
                ->nullable()
                ->after('user_two_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->unique(
                'service_request_proposal_id',
                'conversations_proposal_unique',
            );
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX conversations_general_participants_unique
            ON conversations (
                LEAST(user_one_id, user_two_id),
                GREATEST(user_one_id, user_two_id)
            )
            WHERE service_request_proposal_id IS NULL
        SQL);
    }

    public function down(): void
    {
        DB::table('conversations')
            ->whereNotNull('service_request_proposal_id')
            ->delete();

        DB::statement('DROP INDEX IF EXISTS conversations_general_participants_unique');

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique('conversations_proposal_unique');
            $table->dropConstrainedForeignId('service_request_proposal_id');
            $table->unique(['user_one_id', 'user_two_id']);
        });
    }
};
