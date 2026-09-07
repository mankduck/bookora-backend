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
 Schema::create('booking_status_histories', function (Blueprint $table) {
    $table->id();

    $table->foreignId('booking_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->string('from_status', 30)
        ->nullable();

    $table->string('to_status', 30);

    $table->foreignId('changed_by')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();

    $table->text('note')->nullable();

    $table->timestamp('created_at')->useCurrent();

    $table->index([
        'booking_id',
        'created_at'
    ]);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_status_histories');
    }
};
