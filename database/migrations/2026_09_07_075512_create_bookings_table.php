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
Schema::create('bookings', function (Blueprint $table) {
    $table->id();

    $table->string('booking_code', 50)
        ->unique();

    $table->foreignId('customer_id')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();

    $table->dateTime('start_at');
    $table->dateTime('end_at');

    $table->string('customer_name', 150);
    $table->string('customer_phone', 20);
    $table->string('customer_email', 190)->nullable();

    $table->decimal('subtotal', 15, 2)->default(0);
    $table->decimal('discount_amount', 15, 2)->default(0);
    $table->decimal('total_amount', 15, 2)->default(0);
    $table->decimal('deposit_amount', 15, 2)->default(0);

    $table->foreignId('coupon_id')
        ->nullable()
        ->constrained('coupons')
        ->nullOnDelete();

    $table->text('customer_note')->nullable();
    $table->text('internal_note')->nullable();

    $table->string('status', 30)
        ->default('pending')
        ->index();

    $table->string('payment_status', 30)
        ->default('unpaid')
        ->index();

    $table->string('source', 50)
        ->default('website');

    $table->foreignId('confirmed_by')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();

    $table->dateTime('confirmed_at')->nullable();

    $table->foreignId('cancelled_by')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();

    $table->dateTime('cancelled_at')->nullable();

    $table->text('cancellation_reason')->nullable();

    $table->timestamps();

    $table->index(['start_at', 'end_at']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
