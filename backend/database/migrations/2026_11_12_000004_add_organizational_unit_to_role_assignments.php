<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_role_assignments', function (Blueprint $table) {
            $table->foreignId('organizational_unit_id')->nullable()->after('role_id')
                ->constrained('organizational_units')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_role_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organizational_unit_id');
        });
    }
};
