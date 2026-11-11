<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer reviews typed in from WhatsApp (docs/DATABASE.md §3).
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name', 40);
            // Null = the section's default question.
            $table->string('question')->nullable();
            // Stored with its **marks**; the 220 limit counts without them.
            $table->string('answer', 500);
            $table->string('time', 20)->nullable();
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('position');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
