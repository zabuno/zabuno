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
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->string('token_hash', 64)->nullable()->unique()->after('status');
            $table->timestamp('expires_at')->nullable()->after('token_hash');
            $table->timestamp('accepted_at')->nullable()->after('expires_at');
            $table->foreignId('accepted_by')->nullable()->after('accepted_at')->constrained('users');

            $table->index('expires_at', 'team_invitations_expires_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->dropIndex('team_invitations_expires_at_index');
            $table->dropUnique('team_invitations_token_hash_unique');
        });

        Schema::table('team_invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accepted_by');
        });

        Schema::table('team_invitations', function (Blueprint $table) {
            $table->dropColumn(['token_hash', 'expires_at', 'accepted_at']);
        });
    }
};
