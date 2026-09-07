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
Schema::create('invoice_items', function (Blueprint $table) {
    $table->id();

    $table->foreignId('invoice_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->string('description', 255);

    $table->unsignedInteger('quantity')
        ->default(1);

    $table->decimal('unit_price', 15, 2);

    $table->decimal('subtotal', 15, 2);

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
