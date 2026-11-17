<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_system_admin')->default(false)->after('password');
            $table->boolean('is_active')->default(true)->after('is_system_admin');
            $table->boolean('must_change_password')->default(false)->after('is_active');
        });

        Schema::create('account_security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('second_authorized_person')->nullable();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target_name');
            $table->string('target_email');
            $table->string('event_type', 64);
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_security_events');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_system_admin', 'is_active', 'must_change_password']);
        });
    }
};
