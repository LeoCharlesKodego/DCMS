<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('code', 80)->nullable()->unique()->after('id');
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 120)->unique();
            $table->text('description');
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type', 32);
            $table->timestamps();
            $table->unique(['role_id', 'permission_id', 'scope_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
