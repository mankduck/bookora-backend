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
Schema::create('service_variants', function (Blueprint $table) {
    $table->id();

    $table->foreignId('service_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->string('name', 150);
    $table->string('code', 100)->nullable();

    $table->text('description')->nullable();

    $table->decimal('price', 15, 2);

    $table->decimal('sale_price', 15, 2)
        ->nullable();

    $table->unsignedInteger('duration_minutes');

    $table->string('deposit_type', 30)
        ->nullable();

    $table->decimal('deposit_value', 15, 2)
        ->nullable();

    $table->string('status', 30)
        ->default('active')
        ->index();

    $table->unsignedInteger('sort_order')->default(0);

    $table->timestamps();

    $table->index(['service_id', 'status']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_variants');
    }
};
