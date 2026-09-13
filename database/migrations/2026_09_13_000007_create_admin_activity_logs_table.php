<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('admin_activity_logs', function(Blueprint $t){$t->id();$t->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();$t->string('action',60);$t->string('module',80);$t->text('description');$t->string('method',10)->nullable();$t->string('path',500)->nullable();$t->string('ip_address',64)->nullable();$t->json('meta')->nullable();$t->timestamps();$t->index(['admin_id','created_at']);$t->index(['module','created_at']);}); }
 public function down(): void { Schema::dropIfExists('admin_activity_logs'); }
};
