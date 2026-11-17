<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_migration_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('source_description', 1000)->nullable();
            $table->string('status', 32)->default('open');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('document_migration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_migration_batch_id')->constrained('document_migration_batches')->restrictOnDelete();
            $table->string('original_filename', 255);
            $table->string('storage_key', 512)->unique();
            $table->string('media_type', 160);
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->string('source_location', 1000)->nullable();
            $table->json('source_metadata');
            $table->json('mapped_metadata')->nullable();
            $table->json('duplicate_candidates')->nullable();
            $table->string('verification_status', 32)->default('pending_verification');
            $table->text('verification_notes')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('registered_document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['document_migration_batch_id', 'verification_status']);
            $table->index(['sha256']);
        });
    }

    public function down(): void
    {
        if ((Schema::hasTable('document_migration_items') && DB::table('document_migration_items')->exists())
            || (Schema::hasTable('document_migration_batches') && DB::table('document_migration_batches')->exists())) {
            throw new RuntimeException('Migration batch records and imported source files are retained evidence; export and archive them before rollback.');
        }
        Schema::dropIfExists('document_migration_items');
        Schema::dropIfExists('document_migration_batches');
    }
};
