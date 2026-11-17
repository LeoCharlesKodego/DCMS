<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_task_id')->unique()->constrained('workflow_tasks')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('decision_type', 32);
            $table->text('comment')->nullable();
            $table->timestamp('decided_at');
            $table->index(['actor_user_id', 'decided_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('workflow_decisions') && DB::table('workflow_decisions')->exists()) {
            throw new RuntimeException('Workflow decisions are append-only evidence; export the decision history before rollback.');
        }

        Schema::dropIfExists('workflow_decisions');
    }
};
