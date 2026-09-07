<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                ->constrained('bookings')
                ->cascadeOnDelete();

            $table->foreignId('customer_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->decimal('amount', 15, 2)
                ->default(0);

            $table->decimal('approved_amount', 15, 2)
                ->default(0);

            $table->string('image_path');

            $table->string('status', 30)
                ->default('pending');

            $table->text('customer_note')
                ->nullable();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')
                ->nullable();

            $table->text('rejection_reason')
                ->nullable();

            $table->timestamps();

            $table->index([
                'booking_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_proofs');
    }
};
