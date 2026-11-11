<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menu products (docs/DATABASE.md §3). The slug is the public id, and must
     * also be free in offers.slug (checked by a validation rule).
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            // A category with products can't be deleted: the action answers 409 first, this is the backstop.
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->string('description');
            $table->json('ingredients');
            $table->unsignedInteger('price');
            $table->string('image_path')->nullable();
            $table->string('image_style', 10)->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_best_seller')->default(false);
            $table->unsignedSmallInteger('best_seller_order')->nullable();
            $table->string('sizes_label', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
