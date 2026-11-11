<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A product's sizes (docs/DATABASE.md §3, decision 1.4). Saved by id: cart
     * lines in customers' browsers keep the size id.
     */
    public function up(): void
    {
        Schema::create('product_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('short_name', 20)->nullable();
            // Signed: a smaller size can cost less than the base price.
            $table->integer('price_delta');
            $table->unsignedSmallInteger('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sizes');
    }
};
