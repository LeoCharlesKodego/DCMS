<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Seed only a disposable localhost database with clearly fictional records.
 *
 * The seeder refuses production and is idempotent: running it again refreshes
 * the same fictional institution fixtures instead of duplicating them.
 */
class LocalPhase8DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Refused: LocalPhase8DemoSeeder writes fictional data and is only available outside production.');
        }

        $this->call(OnpRolePermissionSeeder::class);

        DB::table('institutions')->where('id', 1)->update([
            'name' => 'Sample Training Facility',
            'short_name' => 'DEMO',
            'facility_type' => 'Fictional training site',
            'timezone' => 'Asia/Manila',
            'updated_at' => now(),
        ]);

        $unit = DB::table('organizational_units')->where('name', 'Sample Operations Unit')->first();
        $unitId = $unit
            ? (int) $unit->id
            : DB::table('organizational_units')->insertGetId([
                'name' => 'Sample Operations Unit',
                'unit_type' => 'training_unit',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $recordsUnit = DB::table('organizational_units')->where('name', 'Sample Records Unit')->first();
        if (! $recordsUnit) {
            DB::table('organizational_units')->insert([
                'name' => 'Sample Records Unit',
                'unit_type' => 'training_unit',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $type = DB::table('document_types')->where('code', 'PROC')->first();
        $typeId = $type
            ? (int) $type->id
            : DB::table('document_types')->insertGetId([
                'code' => 'PROC',
                'name' => 'Sample Procedure',
                'description' => 'Fictional document type for localhost testing only.',
                'is_active' => true,
                'approval_flow_profile' => 'phase4b_manual_sop_guideline',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $component = function (string $key, ?int $parentId, string $type, string $code, string $name): int {
            $existing = DB::table('document_code_components')->where('source_key', 'LOCAL-DEMO-'.$key)->first();
            if ($existing) {
                return (int) $existing->id;
            }

            return DB::table('document_code_components')->insertGetId([
                'source_key' => 'LOCAL-DEMO-'.$key,
                'parent_service_id' => $parentId,
                'component_type' => $type,
                'code' => $code,
                'name' => $name,
                'source_group' => 'LOCAL FAKE TRAINING DATA',
                'source_flags' => null,
                'is_conflicted' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };
        $serviceId = $component('SERVICE', null, 'service', 'TRN', 'Training Service');
        $component('SECTION', $serviceId, 'section_unit', 'OPS', 'Operations Section');
        $component('DOCTYPE', null, 'document_type', 'PROC', 'Sample Procedure');

        $demoPassword = 'LocalDemo!2026';
        $users = [
            'process.owner@example.test' => ['Sample Process Owner', 'PROCESS_OWNER', $unitId],
            'kmt@example.test' => ['Sample KMT', 'KMT', null],
            'kmt.release@example.test' => ['Sample KMT Release Officer', 'KMT', null],
            'division.head@example.test' => ['Sample Division Head', 'DIVISION_HEAD', $unitId],
            'mcc@example.test' => ['Sample MCC', 'MCC', null],
            'rmic@example.test' => ['Sample RMIC', 'RMIC', null],
        ];

        $accounts = [];
        foreach ($users as $email => [$name, $roleCode, $scopeUnitId]) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make($demoPassword), 'is_active' => true, 'is_system_admin' => false, 'must_change_password' => false],
            );
            $role = Role::query()->where('code', $roleCode)->firstOrFail();
            $user->roleAssignments()->updateOrCreate(
                ['role_id' => $role->id],
                ['organizational_unit_id' => $scopeUnitId, 'work_scope' => 'Fictional localhost test assignment', 'valid_from' => now()->toDateString(), 'valid_until' => null],
            );
            $accounts[$roleCode] = $user;
        }

        // A technical administrator has no document-control role; a staff recipient gets no approval authority.
        $accounts['SYSTEM_ADMIN'] = User::query()->updateOrCreate(
            ['email' => 'system.admin@example.test'],
            ['name' => 'Sample System Administrator', 'password' => Hash::make($demoPassword), 'is_active' => true, 'is_system_admin' => true, 'must_change_password' => false],
        );
        $accounts['STAFF'] = User::query()->updateOrCreate(
            ['email' => 'staff.recipient@example.test'],
            ['name' => 'Sample Staff Recipient', 'password' => Hash::make($demoPassword), 'is_active' => true, 'is_system_admin' => false, 'must_change_password' => false],
        );

        // Fictional D-029 flow. The Process Owner proposes the effective date after approval; KMT confirms and releases it in the lifecycle controller.
        $template = DB::table('workflow_templates')->where('template_key', 'LOCAL-DEMO-PROC')->first();
        $templateId = $template
            ? (int) $template->id
            : DB::table('workflow_templates')->insertGetId([
                'template_key' => 'LOCAL-DEMO-PROC',
                'name' => 'Local Demo Procedure Workflow',
                'description' => 'Fictional localhost-only workflow; not an approved institutional workflow.',
                'is_active' => true,
                'created_by_user_id' => $accounts['KMT']->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $templateVersion = DB::table('workflow_template_versions')
            ->where('workflow_template_id', $templateId)
            ->where('version_no', 1)
            ->first();
        $versionId = $templateVersion
            ? (int) $templateVersion->id
            : DB::table('workflow_template_versions')->insertGetId([
                'workflow_template_id' => $templateId,
                'version_no' => 1,
                'document_type_id' => $typeId,
                'category_id' => null,
                'organizational_unit_id' => null,
                'trigger_event' => 'version.submitted',
                'status' => 'published',
                'published_by_user_id' => $accounts['MCC']->id,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $stages = [
            ['KMT-VERIFY', 'KMT submission verification', 'verification', 'KMT', 'institution'],
            ['DIVISION-REVIEW', 'Owning Division Head review', 'review', 'DIVISION_HEAD', 'organizational_unit'],
            ['MCC-APPROVAL', 'MCC final approval', 'approval', 'MCC', 'institution'],
            ['KMT-RELEASE', 'KMT release', 'release', 'KMT', 'institution'],
        ];
        foreach ($stages as $index => [$key, $name, $action, $roleCode, $scope]) {
            $stage = DB::table('workflow_template_stages')
                ->where('workflow_template_version_id', $versionId)
                ->where('stage_key', $key)
                ->first();
            $stageId = $stage
                ? (int) $stage->id
                : DB::table('workflow_template_stages')->insertGetId([
                    'workflow_template_version_id' => $versionId,
                    'stage_key' => $key,
                    'name' => $name,
                    'purpose' => 'Fictional localhost training step.',
                    'stage_order' => $index + 1,
                    'parallel_group' => null,
                    'action_type' => $action,
                    'is_mandatory' => true,
                    'required_decisions' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            // The demo workflow owns its eligible roles; rewriting them keeps a
            // rerun from leaving a stale role on the stage.
            DB::table('workflow_stage_eligible_roles')->where('workflow_template_stage_id', $stageId)->delete();
            DB::table('workflow_stage_eligible_roles')->insert([
                'workflow_template_stage_id' => $stageId,
                'role_id' => Role::query()->where('code', $roleCode)->value('id'),
                'scope_type' => $scope,
            ]);
        }

        // Drop demo stages a previous revision of this fixture created.
        $staleStageIds = DB::table('workflow_template_stages')
            ->where('workflow_template_version_id', $versionId)
            ->whereNotIn('stage_key', array_column($stages, 0))
            ->pluck('id');
        if ($staleStageIds->isNotEmpty()) {
            DB::table('workflow_stage_eligible_roles')->whereIn('workflow_template_stage_id', $staleStageIds)->delete();
            DB::table('workflow_template_stages')->whereIn('id', $staleStageIds)->delete();
        }
    }
}
