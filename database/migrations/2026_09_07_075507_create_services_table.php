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
Schema::create('services', function (Blueprint $table) {
    $table->id();

    $table->foreignId('category_id')
        ->nullable()
        ->constrained('service_categories')
        ->nullOnDelete();

    $table->string('name', 180);
    $table->string('slug', 200)->unique();

    $table->text('short_description')->nullable();
    $table->longText('description')->nullable();

    $table->string('thumbnail')->nullable();

    $table->decimal('base_price', 15, 2)->default(0);

    $table->unsignedInteger('default_duration_minutes')
        ->default(60);

    $table->string('status', 30)
        ->default('active')
        ->index();

    $table->boolean('is_featured')->default(false);

    $table->unsignedInteger('sort_order')->default(0);

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
