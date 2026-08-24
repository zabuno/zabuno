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
        Schema::create('menu_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces');
            $table->foreignId('menu_id')->constrained('menus');
            $table->foreignId('location_id')->constrained('locations');
            $table->unsignedInteger('version');
            $table->string('state');
            $table->json('snapshot');
            $table->foreignId('published_by')->constrained('users');
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['menu_id', 'version']);
        });

        Schema::create('menu_publication_current_pointers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->unique()->constrained('menus');
            $table->foreignId('current_publication_id')->constrained('menu_publications');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_publication_current_pointers');
        Schema::dropIfExists('menu_publications');
    }
};
