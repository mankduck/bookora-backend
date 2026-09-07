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
Schema::create('coupons', function (Blueprint $table) {
    $table->id();

    $table->string('code', 80)->unique();
    $table->string('name', 150);

    $table->string('type', 30);
    $table->decimal('value', 15, 2);

    $table->decimal('min_order_amount', 15, 2)
        ->nullable();

    $table->decimal('max_discount_amount', 15, 2)
        ->nullable();

    $table->unsignedInteger('usage_limit')->nullable();

    $table->unsignedInteger('usage_per_customer')->nullable();

    $table->dateTime('start_at')->nullable();
    $table->dateTime('end_at')->nullable();

    $table->string('status', 30)
        ->default('active')
        ->index();

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
