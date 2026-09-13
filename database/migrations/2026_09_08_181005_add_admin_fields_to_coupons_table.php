<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (! Schema::hasColumn('coupons', 'description')) {
                $table->text('description')->nullable()->after('name');
            }

            if (! Schema::hasColumn('coupons', 'min_order_amount')) {
                $table->decimal('min_order_amount', 15, 2)->default(0)->after('value');
            }

            if (! Schema::hasColumn('coupons', 'max_discount_amount')) {
                $table->decimal('max_discount_amount', 15, 2)->nullable()->after('min_order_amount');
            }

            if (! Schema::hasColumn('coupons', 'usage_limit')) {
                $table->unsignedInteger('usage_limit')->nullable()->after('max_discount_amount');
            }

            if (! Schema::hasColumn('coupons', 'usage_limit_per_customer')) {
                $table->unsignedInteger('usage_limit_per_customer')->nullable()->after('usage_limit');
            }

            if (! Schema::hasColumn('coupons', 'starts_at')) {
                $table->dateTime('starts_at')->nullable()->after('usage_limit_per_customer');
            }

            if (! Schema::hasColumn('coupons', 'ends_at')) {
                $table->dateTime('ends_at')->nullable()->after('starts_at');
            }

            if (! Schema::hasColumn('coupons', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('ends_at');
            }
        });
    }

    public function down(): void
    {
        // Chủ động không drop các cột trong down vì bảng coupons đã tồn tại từ trước
        // và có thể một số cột đã được tạo bởi migration cũ.
    }
};
