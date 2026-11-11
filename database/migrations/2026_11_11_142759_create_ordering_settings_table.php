<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ordering settings: exactly one row, created by the seeder
     * (docs/DATABASE.md §3). The per-branch dine-in settings are columns on
     * branches.
     */
    public function up(): void
    {
        Schema::create('ordering_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('delivery_enabled')->default(false);
            $table->unsignedInteger('delivery_fee')->default(0);
            // While delivery is on, the site-info save refuses (409) before this phone can go.
            $table->foreignId('delivery_phone_id')->nullable()->constrained('phones')->nullOnDelete();
            $table->boolean('dine_in_enabled')->default(false);
            $table->string('greeting');
            $table->string('closing', 400);
            $table->string('thank_you', 500);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordering_settings');
    }
};
