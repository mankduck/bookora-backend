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
Schema::create('coupon_usages', function (Blueprint $table) {
    $table->id();

    $table->foreignId('coupon_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('booking_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('user_id')
        ->nullable()
        ->constrained()
        ->nullOnDelete();

    $table->decimal('discount_amount', 15, 2);

    $table->dateTime('used_at');

    $table->timestamps();

    $table->unique(['coupon_id', 'booking_id']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};
