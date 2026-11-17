<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table): void {
            $table->string('approval_flow_profile', 40)->default('source_defined');
        });
        Schema::create('submission_validation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_file_id')->constrained('document_files')->restrictOnDelete();
            $table->unsignedInteger('run_number');
            $table->string('status', 40);
            $table->string('code_source', 20)->nullable();
            $table->string('extracted_code', 512)->nullable();
            $table->json('findings');
            $table->foreignId('checked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at');
            $table->timestamps();
            $table->unique(['document_file_id', 'run_number']);
        });
        Schema::table('document_files', function (Blueprint $table): void {
            $table->boolean('is_current')->default(true);
        });
        Schema::create('phase4b_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('phase4b_setting_history', function (Blueprint $table): void {
            $table->id();
            $table->string('key');
            $table->text('old_value')->nullable();
            $table->text('new_value');
            $table->foreignId('changed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phase4b_setting_history');
        Schema::dropIfExists('phase4b_settings');
        Schema::dropIfExists('submission_validation_runs');
        Schema::table('document_files', fn (Blueprint $table) => $table->dropColumn('is_current'));
        Schema::table('document_types', fn (Blueprint $table) => $table->dropColumn('approval_flow_profile'));
    }
};
