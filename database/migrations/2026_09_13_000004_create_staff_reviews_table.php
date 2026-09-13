<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff_profiles')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('status', 30)->default('published');
            $table->timestamps();
            $table->unique(['booking_id', 'staff_id']);
            $table->index(['staff_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_reviews');
    }
};
