<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_modules', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('title')->nullable();
            $table->longText('content')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_locked')->default(false);
            $table->boolean('show_in_nav')->default(true);
            $table->string('nav_label')->nullable();
            $table->timestamps();
            $table->index(['is_enabled', 'sort_order']);
        });

        $now = now();
        DB::table('homepage_modules')->insert([
            [
                'type' => 'hero',
                'name' => 'Trang chủ',
                'slug' => 'hero',
                'title' => 'Đặt lịch dịch vụ đơn giản hơn.',
                'content' => 'Chọn dịch vụ, gói phù hợp và khung giờ còn trống.',
                'settings' => json_encode(['button_label' => 'Đặt lịch ngay']),
                'sort_order' => 10,
                'is_enabled' => true,
                'is_locked' => true,
                'show_in_nav' => false,
                'nav_label' => 'Trang chủ',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'services',
                'name' => 'Dịch vụ',
                'slug' => 'services',
                'title' => 'Dịch vụ nổi bật',
                'content' => null,
                'settings' => json_encode(['limit' => 6]),
                'sort_order' => 20,
                'is_enabled' => true,
                'is_locked' => true,
                'show_in_nav' => true,
                'nav_label' => 'Dịch vụ',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'top_staff',
                'name' => 'Photo nổi bật',
                'slug' => 'top-staff',
                'title' => 'Photo được đánh giá cao',
                'content' => null,
                'settings' => json_encode(['limit' => 4, 'minimum_reviews' => 1]),
                'sort_order' => 30,
                'is_enabled' => true,
                'is_locked' => true,
                'show_in_nav' => true,
                'nav_label' => 'Photo nổi bật',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'posts',
                'name' => 'Bài viết',
                'slug' => 'posts',
                'title' => 'Bài viết mới',
                'content' => null,
                'settings' => json_encode(['limit' => 3]),
                'sort_order' => 40,
                'is_enabled' => true,
                'is_locked' => true,
                'show_in_nav' => true,
                'nav_label' => 'Bài viết',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_modules');
    }
};
