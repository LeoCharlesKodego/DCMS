<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_version_id')->constrained('document_versions')->restrictOnDelete();
            $table->foreignId('issued_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->index(['document_version_id', 'issued_at']);
        });

        Schema::create('distribution_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_distribution_id')->constrained('document_distributions')->restrictOnDelete();
            $table->string('target_type', 32);
            $table->foreignId('organizational_unit_id')->nullable()->constrained('organizational_units')->restrictOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('target_label_snapshot', 255);
            $table->timestamp('created_at');
            $table->unique(['document_distribution_id', 'organizational_unit_id'], 'distribution_unit_target_unique');
            $table->unique(['document_distribution_id', 'role_id'], 'distribution_role_target_unique');
            $table->unique(['document_distribution_id', 'user_id'], 'distribution_user_target_unique');
            $table->index(['target_type', 'organizational_unit_id', 'role_id', 'user_id'], 'distribution_target_lookup_idx');
        });

        Schema::create('distribution_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_distribution_id')->constrained('document_distributions')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('recipient_name_at_issue', 255);
            $table->boolean('acknowledgment_required')->default(true);
            $table->date('due_at')->nullable();
            $table->string('acknowledgment_status', 24)->default('pending');
            $table->foreignId('acknowledged_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('exempted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('exempted_at')->nullable();
            $table->text('exemption_reason')->nullable();
            $table->timestamps();
            $table->unique(['document_distribution_id', 'user_id'], 'distribution_recipient_unique');
            $table->index(['user_id', 'acknowledgment_status', 'due_at'], 'distribution_recipient_queue_idx');
        });

        Schema::create('distribution_recipient_sources', function (Blueprint $table) {
            $table->foreignId('distribution_recipient_id')->constrained('distribution_recipients')->restrictOnDelete();
            $table->foreignId('distribution_target_id')->constrained('distribution_targets')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->primary(['distribution_recipient_id', 'distribution_target_id'], 'distribution_recipient_source_pk');
            $table->index('distribution_target_id');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE distribution_targets ADD CONSTRAINT distribution_target_type_check CHECK ((target_type = 'organizational_unit' AND organizational_unit_id IS NOT NULL AND role_id IS NULL AND user_id IS NULL) OR (target_type = 'role' AND organizational_unit_id IS NULL AND role_id IS NOT NULL AND user_id IS NULL) OR (target_type = 'user' AND organizational_unit_id IS NULL AND role_id IS NULL AND user_id IS NOT NULL))");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('document_distributions') && DB::table('document_distributions')->exists()) {
            throw new RuntimeException('Distribution and acknowledgment records are operational history; export them before rollback.');
        }

        Schema::dropIfExists('distribution_recipient_sources');
        Schema::dropIfExists('distribution_recipients');
        Schema::dropIfExists('distribution_targets');
        Schema::dropIfExists('document_distributions');
    }
};
