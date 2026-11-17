<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OnpRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'document.code.assign' => 'KMT assigns document codes after validating the DRRF.',
            'document.registry.view' => 'Access to register records required by the explicitly assigned ONP document-control duty.',
            'document.register' => 'KMT registers documents and maintains the document-control records.',
            'document.release.registered' => 'The ONP DRRF assigns registration and release to KMT.',
            'document.stamp' => 'KMT stamps controlled documents.',
            'document.distribute' => 'KMT distributes and controls documents.',
            'document.audit.annual' => 'Annual physical inventory and register audit duty stated for KMT and QMR.',
            'document.create_revise' => 'Process Owner creates and revises unit documents and maintains content accuracy.',
            'document.review.periodic' => 'Process Owner reviews each document every three years or sooner.',
            'document.copy_list.maintain' => 'Process Owner maintains the unit list of authorized copies.',
            'document.review.verify' => 'QMR reviews and verifies documentation before final approval.',
            'document.submission.verify' => 'KMT verifies submission checks and returns submissions for revision.',
            'document.review.division_head' => 'Owning Division Head reviews manuals, SOPs, and guidelines under Phase 4B.',
            'document.master_copy.approve.manual_sop_guideline' => 'MCC is final approver for Master Copies of manuals, SOPs, and guidelines.',
            'document.code.consult.division' => 'Division Head is consulted on division-specific codes and high-risk content.',
            'document.review.policy_specific_guideline_template' => 'Department Head and Service Chief appear as reviewers on policy and specific-guideline templates.',
            'document.code.approve.high_risk' => 'Quality Management Committee approves codes for high-risk documents.',
            'qms.requirements.identify' => 'Management Committee identifies required QMS documentation.',
            'form.internal.approve_before_coding' => 'Forms Committee approves internal forms before KMT coding.',
            'document.copy.safeguard' => 'Authorized Person safeguards authorized copies in their unit.',
            'records.manage' => 'KMT manages operational records and approves disposition under owner decision D-025.',
            'document.cascade' => 'Supervisor or Unit Section Head cascades documents to staff.',
        ];

        $roles = [
            'MCC' => ['Medical Center Chief (MCC)', ['document.master_copy.approve.manual_sop_guideline' => 'institution', 'document.registry.view' => 'institution']],
            'QMR' => ['Quality Management Representative (QMR)', ['document.review.verify' => 'institution', 'document.audit.annual' => 'institution', 'document.registry.view' => 'institution']],
            'KMT' => ['Knowledge Management Team (KMT)', ['document.code.assign' => 'institution', 'document.submission.verify' => 'institution', 'document.register' => 'institution', 'document.release.registered' => 'institution', 'document.stamp' => 'institution', 'document.distribute' => 'institution', 'document.audit.annual' => 'institution', 'document.registry.view' => 'institution', 'records.manage' => 'institution']],
            'PROCESS_OWNER' => ['Process Owner', ['document.create_revise' => 'organizational_unit', 'document.review.periodic' => 'organizational_unit', 'document.copy_list.maintain' => 'organizational_unit', 'document.registry.view' => 'organizational_unit']],
            'DIVISION_HEAD' => ['Division Head', ['document.code.consult.division' => 'organizational_unit', 'document.review.division_head' => 'organizational_unit', 'document.registry.view' => 'organizational_unit']],
            'DEPARTMENT_HEAD' => ['Department Head', ['document.review.policy_specific_guideline_template' => 'organizational_unit', 'document.registry.view' => 'organizational_unit']],
            'SERVICE_CHIEF' => ['Service Chief', ['document.review.policy_specific_guideline_template' => 'organizational_unit', 'document.registry.view' => 'organizational_unit']],
            'QUALITY_MANAGEMENT_COMMITTEE' => ['Quality Management Committee', ['document.code.approve.high_risk' => 'institution']],
            'MANAGEMENT_COMMITTEE' => ['Management Committee', ['qms.requirements.identify' => 'institution']],
            'FORMS_COMMITTEE' => ['Forms Committee', ['form.internal.approve_before_coding' => 'institution']],
            'RMIC' => ['RMIC (Records Management Improvement Committee)', []],
            'AUTHORIZED_PERSON' => ['Authorized Person', ['document.copy.safeguard' => 'organizational_unit']],
            'SUPERVISOR_UNIT_SECTION_HEAD' => ['Supervisor / Unit Section Head', ['document.cascade' => 'organizational_unit']],
        ];

        DB::transaction(function () use ($permissions, $roles): void {
            $permissionModels = [];
            foreach ($permissions as $code => $description) {
                $permissionModels[$code] = Permission::query()->updateOrCreate(['code' => $code], ['description' => $description]);
            }

            foreach ($roles as $code => [$name, $grants]) {
                $role = Role::query()->where('code', $code)->first();
                if (! $role) {
                    $role = Role::query()->firstOrNew(['name' => $name]);
                }
                $role->fill(['code' => $code, 'name' => $name, 'is_active' => true])->save();

                $sync = [];
                foreach ($grants as $permissionCode => $scopeType) {
                    $sync[$permissionModels[$permissionCode]->id] = ['scope_type' => $scopeType];
                }
                $role->permissions()->sync($sync);
            }
        });
    }
}
