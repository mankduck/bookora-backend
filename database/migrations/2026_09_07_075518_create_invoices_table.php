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
Schema::create('invoices', function (Blueprint $table) {
    $table->id();

    $table->string('invoice_code', 50)
        ->unique();

    $table->foreignId('booking_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('customer_id')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();

    $table->decimal('subtotal', 15, 2)->default(0);
    $table->decimal('discount_amount', 15, 2)->default(0);
    $table->decimal('tax_amount', 15, 2)->default(0);
    $table->decimal('total_amount', 15, 2)->default(0);

    $table->decimal('paid_amount', 15, 2)->default(0);
    $table->decimal('remaining_amount', 15, 2)->default(0);

    $table->string('status', 30)
        ->default('unpaid')
        ->index();

    $table->dateTime('issued_at')->nullable();
    $table->dateTime('due_at')->nullable();

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
