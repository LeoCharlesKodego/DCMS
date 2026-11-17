<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_code_components', function (Blueprint $table) {
            $table->id();
            $table->string('source_key', 64)->unique();
            $table->foreignId('parent_service_id')->nullable()->constrained('document_code_components')->restrictOnDelete();
            $table->string('component_type', 32);
            $table->string('code', 32);
            $table->string('name', 255);
            $table->string('source_group', 255);
            $table->string('source_flags', 100)->nullable();
            $table->boolean('is_conflicted')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['component_type', 'is_active', 'is_conflicted']);
            $table->index(['parent_service_id', 'component_type', 'is_active']);
            $table->index('code');
        });

        Schema::create('document_code_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('code_prefix', 255)->unique();
            $table->unsignedSmallInteger('next_sequence')->default(1);
            $table->timestamps();
        });

        Schema::create('document_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->nullable();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'name']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained('document_types')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('document_categories')->restrictOnDelete();
            $table->foreignId('classification_id')->nullable()->constrained('document_classifications')->restrictOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('owning_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('registered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 500);
            $table->string('current_revision_label', 16)->default('1.0');
            $table->string('status', 32)->default('pending_code');
            $table->date('effective_date')->nullable();
            $table->unsignedSmallInteger('retention_years')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
            $table->index(['owning_unit_id', 'status']);
            $table->index(['document_type_id', 'status']);
        });

        Schema::create('master_code_register_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->unique()->constrained('documents')->restrictOnDelete();
            $table->foreignId('service_component_id')->constrained('document_code_components')->restrictOnDelete();
            $table->foreignId('section_component_id')->constrained('document_code_components')->restrictOnDelete();
            $table->foreignId('unit_component_id')->nullable()->constrained('document_code_components')->restrictOnDelete();
            $table->foreignId('document_type_component_id')->constrained('document_code_components')->restrictOnDelete();
            $table->string('code_prefix', 255);
            $table->unsignedSmallInteger('sequence_number');
            $table->string('base_code', 512)->unique();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('assigned_at');
            $table->timestamps();
            $table->unique(['code_prefix', 'sequence_number']);
            $table->index(['assigned_by_user_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_code_register_entries');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_classifications');
        Schema::dropIfExists('document_code_sequences');
        Schema::dropIfExists('document_code_components');
    }
};
