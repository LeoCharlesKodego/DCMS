<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->string('target_type', 80);
            $table->unsignedBigInteger('target_id');
            $table->foreignId('document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 120);
            $table->string('from_state', 32)->nullable();
            $table->string('to_state', 32)->nullable();
            $table->timestamp('occurred_at');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->index(['target_type', 'target_id', 'occurred_at']);
            $table->index(['document_id', 'occurred_at']);
            $table->index(['actor_user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('audit_events') && \Illuminate\Support\Facades\DB::table('audit_events')->exists()) {
            throw new \RuntimeException('Audit history is append-only; export and archive it before rolling back this migration.');
        }

        Schema::dropIfExists('audit_events');
    }
};
