<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restaurant branches (docs/DATABASE.md §3). The slug is the public id and
     * is printed in the table QR codes (/t/<slug>/<n>): never regenerated.
     * The dine-in settings are columns here (decision 1.1), so they go when
     * the branch goes.
     */
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 60);
            $table->string('address', 160);
            $table->string('map_url', 500)->nullable();
            $table->boolean('dine_in_enabled')->default(false);
            $table->unsignedTinyInteger('table_count')->default(0);
            $table->unsignedSmallInteger('position');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
