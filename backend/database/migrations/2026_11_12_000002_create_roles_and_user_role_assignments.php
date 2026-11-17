<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->string('work_scope', 255);
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'valid_from', 'valid_until']);
            $table->index(['role_id', 'valid_from', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_assignments');
        Schema::dropIfExists('roles');
    }
};
