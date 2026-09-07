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
Schema::create('service_categories', function (Blueprint $table) {
    $table->id();

    $table->foreignId('parent_id')
        ->nullable()
        ->constrained('service_categories')
        ->nullOnDelete();

    $table->string('name', 150);
    $table->string('slug', 180)->unique();

    $table->text('description')->nullable();

    $table->string('thumbnail')->nullable();

    $table->unsignedInteger('sort_order')->default(0);

    $table->string('status', 30)
        ->default('active')
        ->index();

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_categories');
    }
};
