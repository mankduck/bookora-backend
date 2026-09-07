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
Schema::create('booking_items', function (Blueprint $table) {
    $table->id();

    $table->foreignId('booking_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('service_id')
        ->nullable()
        ->constrained()
        ->nullOnDelete();

    $table->foreignId('service_variant_id')
        ->nullable()
        ->constrained()
        ->nullOnDelete();

    $table->string('service_name', 180);
    $table->string('variant_name', 150)->nullable();

    $table->decimal('price', 15, 2);

    $table->unsignedInteger('quantity')
        ->default(1);

    $table->unsignedInteger('duration_minutes');

    $table->decimal('subtotal', 15, 2);

    $table->timestamps();

    $table->index('booking_id');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_items');
    }
};
