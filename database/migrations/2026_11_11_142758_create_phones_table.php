<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restaurant and branch phones (docs/DATABASE.md §3). A phone with a branch
     * is that branch's floor staff number: it gets the branch's table orders
     * and is never public. Its position decides which one (the first with WhatsApp).
     */
    public function up(): void
    {
        Schema::create('phones', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20);
            $table->boolean('has_whatsapp')->default(false);
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phones');
    }
};
