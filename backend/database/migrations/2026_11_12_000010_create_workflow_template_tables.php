<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_key', 64)->unique();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'name']);
        });

        Schema::create('workflow_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_template_id')->constrained('workflow_templates')->restrictOnDelete();
            $table->unsignedInteger('version_no');
            $table->foreignId('document_type_id')->constrained('document_types')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('document_categories')->restrictOnDelete();
            $table->foreignId('organizational_unit_id')->nullable()->constrained('organizational_units')->restrictOnDelete();
            $table->string('trigger_event', 64)->default('version.submitted');
            $table->string('status', 24)->default('draft');
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['workflow_template_id', 'version_no']);
            $table->index(['document_type_id', 'category_id', 'organizational_unit_id', 'trigger_event', 'status'], 'workflow_version_applicability_idx');
        });

        Schema::create('workflow_template_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_template_version_id')->constrained('workflow_template_versions')->restrictOnDelete();
            $table->string('stage_key', 64);
            $table->string('name', 180);
            $table->text('purpose')->nullable();
            $table->unsignedSmallInteger('stage_order');
            $table->unsignedSmallInteger('parallel_group')->nullable();
            $table->string('action_type', 32);
            $table->boolean('is_mandatory')->default(true);
            $table->unsignedSmallInteger('required_decisions')->default(1);
            $table->timestamps();
            $table->unique(['workflow_template_version_id', 'stage_key']);
            $table->index(['workflow_template_version_id', 'action_type', 'stage_order'], 'workflow_stage_action_order_idx');
        });

        Schema::create('workflow_stage_eligible_roles', function (Blueprint $table) {
            $table->foreignId('workflow_template_stage_id')->constrained('workflow_template_stages')->restrictOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->string('scope_type', 32);
            $table->primary(['workflow_template_stage_id', 'role_id']);
            $table->index(['role_id', 'scope_type']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('workflow_instances') && DB::table('workflow_instances')->exists()) {
            throw new RuntimeException('Workflow instances reference template versions; export or migrate those instances before rollback.');
        }

        if (Schema::hasTable('audit_events') && DB::table('audit_events')
            ->whereIn('target_type', ['workflow_template', 'workflow_template_version', 'workflow_template_stage'])
            ->exists()) {
            throw new RuntimeException('Export workflow template audit evidence before rollback.');
        }

        Schema::dropIfExists('workflow_stage_eligible_roles');
        Schema::dropIfExists('workflow_template_stages');
        Schema::dropIfExists('workflow_template_versions');
        Schema::dropIfExists('workflow_templates');
    }
};
