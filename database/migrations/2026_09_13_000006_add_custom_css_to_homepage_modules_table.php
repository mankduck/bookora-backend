<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('homepage_modules', fn(Blueprint $t) => $t->longText('custom_css')->nullable()->after('content')); }
 public function down(): void { Schema::table('homepage_modules', fn(Blueprint $t) => $t->dropColumn('custom_css')); }
};
