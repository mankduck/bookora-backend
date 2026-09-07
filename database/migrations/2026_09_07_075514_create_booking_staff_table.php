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
Schema::create('booking_staff', function (Blueprint $table) {
    $table->id();

    $table->foreignId('booking_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('staff_id')
        ->constrained('staff_profiles')
        ->cascadeOnDelete();

    $table->string('role', 100)->nullable();

    $table->boolean('is_primary')->default(false);

    $table->foreignId('assigned_by')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();

    $table->dateTime('assigned_at')->nullable();

    $table->timestamps();

    $table->unique(['booking_id', 'staff_id']);

    $table->index([
        'staff_id',
        'booking_id'
    ]);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_staff');
    }
};
