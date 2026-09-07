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
Schema::create('staff_time_offs', function (Blueprint $table) {
    $table->id();

    $table->foreignId('staff_id')
        ->constrained('staff_profiles')
        ->cascadeOnDelete();

    $table->dateTime('start_at');
    $table->dateTime('end_at');

    $table->text('reason')->nullable();

    $table->string('status', 30)
        ->default('approved');

    $table->foreignId('created_by')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();

    $table->timestamps();

    $table->index(['staff_id', 'start_at', 'end_at']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_time_offs');
    }
};
