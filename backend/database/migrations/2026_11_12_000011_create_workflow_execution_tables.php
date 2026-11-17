<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_version_id')->unique()->constrained('document_versions')->restrictOnDelete();
            $table->foreignId('workflow_template_version_id')->constrained('workflow_template_versions')->restrictOnDelete();
            $table->string('status', 24)->default('in_progress');
            $table->foreignId('started_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'started_at']);
        });

        Schema::create('workflow_stage_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained('workflow_instances')->restrictOnDelete();
            $table->foreignId('workflow_template_stage_id')->constrained('workflow_template_stages')->restrictOnDelete();
            $table->unsignedInteger('pass_no')->default(1);
            $table->string('status', 24)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['workflow_instance_id', 'workflow_template_stage_id', 'pass_no'], 'workflow_stage_instance_pass_unique');
            $table->index(['workflow_instance_id', 'status']);
        });

        Schema::create('workflow_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_stage_instance_id')->constrained('workflow_stage_instances')->restrictOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('organizational_unit_id')->nullable()->constrained('organizational_units')->restrictOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('pending');
            $table->timestamp('assigned_at');
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
            $table->index(['assigned_user_id', 'status']);
            $table->index(['role_id', 'organizational_unit_id', 'status'], 'workflow_task_role_scope_status_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('workflow_instances') && DB::table('workflow_instances')->exists()) {
            throw new RuntimeException('Workflow instances and task assignments are operational records; export them before rollback.');
        }

        Schema::dropIfExists('workflow_tasks');
        Schema::dropIfExists('workflow_stage_instances');
        Schema::dropIfExists('workflow_instances');
    }
};
