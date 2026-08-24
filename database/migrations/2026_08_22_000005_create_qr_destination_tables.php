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
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces');
            $table->foreignId('location_id')->constrained('locations');
            $table->string('token', 43)->unique();
            $table->string('state');
            $table->timestamps();
        });

        Schema::create('qr_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_code_id')->constrained('qr_codes');
            $table->string('destination_type');
            $table->foreignId('menu_id')->nullable()->constrained('menus');
            $table->timestamps();
        });

        Schema::create('qr_code_current_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_code_id')->unique()->constrained('qr_codes');
            $table->foreignId('qr_destination_id')->constrained('qr_destinations');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qr_code_current_destinations');
        Schema::dropIfExists('qr_destinations');
        Schema::dropIfExists('qr_codes');
    }
};
