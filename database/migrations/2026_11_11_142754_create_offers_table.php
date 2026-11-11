<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Buy X, get Y free" offers (docs/DATABASE.md §3). The slug is the public
     * id and shares the /menu/<slug> space with products.slug.
     */
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title', 60);
            $table->json('items');
            $table->string('free_item', 80);
            $table->unsignedInteger('price');
            $table->string('image_path');
            $table->string('image_style', 10)->nullable();
            $table->string('badge', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position');
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
