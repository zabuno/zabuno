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
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->nullable()->unique();
            $table->foreignId('workspace_id')->constrained('workspaces');
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('qr_code_id')->constrained('qr_codes');
            $table->foreignId('menu_id')->constrained('menus');
            $table->string('event_type');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['workspace_id', 'location_id', 'occurred_at'], 'analytics_events_scope_range_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
