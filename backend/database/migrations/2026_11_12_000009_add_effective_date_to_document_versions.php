<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->date('proposed_effective_date')->nullable()->after('revision_label');
            $table->date('effective_date')->nullable()->after('proposed_effective_date');
        });
    }

    public function down(): void
    {
        if ((Schema::hasTable('document_versions') && (DB::table('document_versions')->whereNotNull('effective_date')->exists()
                || DB::table('document_versions')->whereNotNull('proposed_effective_date')->exists()))
            || (Schema::hasTable('audit_events') && DB::table('audit_events')->exists())) {
            throw new \RuntimeException('Export version effective dates and their audit events before rolling back this migration.');
        }

        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropColumn(['proposed_effective_date', 'effective_date']);
        });
    }
};
