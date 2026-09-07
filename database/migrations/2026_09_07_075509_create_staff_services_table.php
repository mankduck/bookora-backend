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
Schema::create('staff_services', function (Blueprint $table) {
    $table->id();

    $table->foreignId('staff_id')
        ->constrained('staff_profiles')
        ->cascadeOnDelete();

    $table->foreignId('service_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->unsignedInteger('custom_duration_minutes')
        ->nullable();

    $table->string('status', 30)
        ->default('active');

    $table->timestamps();

    $table->unique(['staff_id', 'service_id']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_services');
    }
};
