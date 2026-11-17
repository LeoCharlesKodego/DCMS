<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTemplateVersion;
use App\Services\WorkflowTemplateConfigurationService;
use Database\Seeders\OnpRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase4BWorkflowProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase4b_profile_requires_kmt_then_owning_division_head_then_mcc_and_excludes_qmr(): void
    {
        $this->seed(OnpRolePermissionSeeder::class);
        $owner = User::factory()->create();
        $type = DocumentType::query()->create(['code' => 'GUID', 'name' => 'Ordinary guideline', 'approval_flow_profile' => 'phase4b_manual_sop_guideline', 'is_active' => true]);
        $template = WorkflowTemplate::query()->create(['template_key' => 'TEST-PHASE4B', 'name' => 'Test Phase 4B', 'is_active' => true, 'created_by_user_id' => $owner->id]);
        $version = WorkflowTemplateVersion::query()->create(['workflow_template_id' => $template->id, 'version_no' => 1, 'document_type_id' => $type->id, 'trigger_event' => 'version.submitted', 'status' => 'draft']);
        $roles = [
            ['KMT', 'verification', 'institution'],
            ['DIVISION_HEAD', 'review', 'organizational_unit'],
            ['MCC', 'approval', 'institution'],
            ['KMT', 'release', 'institution'],
        ];
        foreach ($roles as $index => [$roleCode, $action, $scope]) {
            $stage = $version->stages()->create([
                'stage_key' => 'stage-'.$index, 'name' => $action, 'stage_order' => $index + 1,
                'action_type' => $action, 'is_mandatory' => true, 'required_decisions' => 1,
            ]);
            $stage->eligibleRoles()->attach(Role::query()->where('code', $roleCode)->value('id'), ['scope_type' => $scope]);
        }

        app(WorkflowTemplateConfigurationService::class)->assertPublishable($version->fresh());
        $qmr = Role::query()->where('code', 'QMR')->firstOrFail();
        $qmrStage = $version->stages()->create(['stage_key' => 'qmr', 'name' => 'QMR', 'stage_order' => 5, 'action_type' => 'review', 'is_mandatory' => true, 'required_decisions' => 1]);
        $qmrStage->eligibleRoles()->attach($qmr->id, ['scope_type' => 'institution']);
        $this->expectException(ValidationException::class);
        app(WorkflowTemplateConfigurationService::class)->assertPublishable($version->fresh());
    }
}
