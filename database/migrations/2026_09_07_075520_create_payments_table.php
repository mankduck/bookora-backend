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
Schema::create('payments', function (Blueprint $table) {
    $table->id();

    $table->string('payment_code', 50)
        ->unique();

    $table->foreignId('booking_id')
        ->nullable()
        ->constrained()
        ->nullOnDelete();

    $table->foreignId('invoice_id')
        ->nullable()
        ->constrained()
        ->nullOnDelete();

    $table->foreignId('customer_id')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();

    $table->string('method', 50);

    $table->decimal('amount', 15, 2);

    $table->string('transaction_id', 255)
        ->nullable();

    $table->string('status', 30)
        ->default('pending')
        ->index();

    $table->text('note')->nullable();

    $table->dateTime('paid_at')->nullable();

    $table->foreignId('created_by')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
