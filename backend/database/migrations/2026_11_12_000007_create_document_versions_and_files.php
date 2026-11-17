<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->restrictOnDelete();
            $table->unsignedInteger('sequence_no');
            $table->string('revision_label', 16);
            $table->string('version_code', 512)->nullable();
            $table->text('change_summary')->nullable();
            $table->string('status', 32)->default('draft');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['document_id', 'sequence_no']);
            $table->index(['document_id', 'status']);
        });

        Schema::create('document_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_version_id')->constrained('document_versions')->restrictOnDelete();
            $table->string('original_filename', 255);
            $table->string('storage_key', 512)->unique();
            $table->string('media_type', 160);
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamps();
            $table->index(['document_version_id', 'uploaded_at']);
        });
    }

    public function down(): void
    {
        if ((Schema::hasTable('document_files') && DB::table('document_files')->exists())
            || (Schema::hasTable('document_versions') && DB::table('document_versions')->exists())) {
            throw new \RuntimeException('Back up and archive document metadata and private files before rolling back versioned storage.');
        }

        Schema::dropIfExists('document_files');
        Schema::dropIfExists('document_versions');
    }
};
