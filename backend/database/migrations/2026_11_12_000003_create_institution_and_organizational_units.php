<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name', 255);
            $table->string('short_name', 80)->nullable();
            $table->text('contact_details')->nullable();
            $table->string('facility_type', 120)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('status', 32)->default('active');
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });

        DB::table('institutions')->insert([
            'id' => 1,
            'name' => 'Ospital ng Palawan',
            'short_name' => 'ONP',
            'contact_details' => null,
            'facility_type' => 'DOH hospital',
            'timezone' => null,
            'status' => 'active',
            'logo_path' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('organizational_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('organizational_units')->restrictOnDelete();
            $table->string('name', 180);
            $table->string('unit_type', 80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['parent_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizational_units');
        Schema::dropIfExists('institutions');
    }
};
