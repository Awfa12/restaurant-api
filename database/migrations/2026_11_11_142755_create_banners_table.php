<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menu banner slides (docs/DATABASE.md §3, decision 1.5). A button stores
     * its target as a key, never a link: when the target is deleted the key
     * becomes null and the public response drops the button.
     */
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('eyebrow', 50)->nullable();
            $table->string('title', 60);
            $table->string('image_path');
            $table->string('image_alt', 120);
            $table->string('action_label', 40)->nullable();
            $table->string('target_kind', 10)->nullable();
            $table->foreignId('target_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('target_product_id')->nullable()->constrained('products')->nullOnDelete();
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
        Schema::dropIfExists('banners');
    }
};
