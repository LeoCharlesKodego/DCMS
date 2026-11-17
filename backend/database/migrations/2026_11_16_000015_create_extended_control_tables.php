<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_references', function (Blueprint $table) {
            $table->id();
            $table->string('title', 500);
            $table->string('issuing_body', 255)->nullable();
            $table->string('reference_number', 255)->nullable();
            $table->string('url', 2048)->nullable();
            $table->date('published_at')->nullable();
            $table->date('last_reviewed_at')->nullable();
            $table->date('review_due_at')->nullable();
            $table->string('status', 32)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'review_due_at']);
        });
        Schema::create('document_external_references', function (Blueprint $table) {
            $table->foreignId('document_id')->constrained('documents')->restrictOnDelete();
            $table->foreignId('external_reference_id')->constrained('external_references')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['document_id', 'external_reference_id']);
        });

        Schema::create('retention_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('record_type', 255);
            $table->unsignedInteger('retention_duration')->nullable();
            $table->string('retention_unit', 16)->nullable();
            $table->string('trigger_event', 100);
            $table->text('legal_basis')->nullable();
            $table->foreignId('responsible_unit_id')->nullable()->constrained('organizational_units')->restrictOnDelete();
            $table->string('disposition_method', 255)->nullable();
            $table->string('approval_authority', 255)->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->index(['record_type', 'is_active']);
        });
        Schema::create('operational_records', function (Blueprint $table) {
            $table->id();
            $table->string('record_type', 255);
            $table->string('title', 500);
            $table->string('reference_number', 255)->nullable();
            $table->foreignId('retention_schedule_id')->nullable()->constrained('retention_schedules')->restrictOnDelete();
            $table->foreignId('owning_unit_id')->nullable()->constrained('organizational_units')->restrictOnDelete();
            $table->date('created_on')->nullable();
            $table->date('retention_triggered_on')->nullable();
            $table->string('status', 32)->default('active');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'retention_triggered_on']);
        });
        Schema::create('operational_record_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_record_id')->constrained('operational_records')->restrictOnDelete();
            $table->text('reason');
            $table->string('authority_reference', 500)->nullable();
            $table->foreignId('placed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('placed_at');
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->text('release_reason')->nullable();
            $table->timestamps();
            $table->index(['operational_record_id', 'released_at']);
        });
        Schema::create('disposition_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_record_id')->constrained('operational_records')->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 24)->default('pending');
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->foreignId('executed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('executed_at')->nullable();
            $table->text('execution_evidence')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('event_type', 100);
            $table->string('title', 255);
            $table->text('body');
            $table->string('target_type', 80)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at', 'created_at']);
        });
        Schema::create('backup_run_evidence', function (Blueprint $table) {
            $table->id();
            $table->string('run_identifier', 255)->unique();
            $table->string('status', 24);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('database_sha256', 64)->nullable();
            $table->string('documents_sha256', 64)->nullable();
            $table->unsignedInteger('document_file_count')->nullable();
            $table->string('destination_label', 500)->nullable();
            $table->text('failure_summary')->nullable();
            $table->timestamps();
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        foreach (['backup_run_evidence', 'user_notifications', 'disposition_requests', 'operational_record_holds', 'operational_records', 'retention_schedules', 'document_external_references', 'external_references'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Extended-control records are operational evidence; export and archive before rollback.');
            }
        }
        foreach (['backup_run_evidence', 'user_notifications', 'disposition_requests', 'operational_record_holds', 'operational_records', 'retention_schedules', 'document_external_references', 'external_references'] as $table) Schema::dropIfExists($table);
    }
};
