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
Schema::create('staff_profiles', function (Blueprint $table) {
    $table->id();

    $table->foreignId('user_id')
        ->unique()
        ->constrained()
        ->cascadeOnDelete();

    $table->string('employee_code', 50)
        ->nullable()
        ->unique();

    $table->string('position', 100)->nullable();

    $table->text('bio')->nullable();

    $table->unsignedInteger('experience_years')->default(0);

    $table->boolean('is_bookable')->default(true);

    $table->string('status', 30)
        ->default('active')
        ->index();

    $table->unsignedInteger('sort_order')->default(0);

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
