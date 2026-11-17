<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RetentionSchedule;
use App\Models\User;
use App\Services\OperationalRecordsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OperationalRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_administrator_without_kmt_record_permission_is_denied(): void
    {
        $admin = $this->user('sysadmin@example.test');
        $admin->update(['is_system_admin' => true]);
        $this->actingAs($admin)->getJson('/api/operational-records')->assertForbidden();
    }

    public function test_holds_block_disposition_and_retention_schedules_remain_inactive(): void
    {
        $kmt = $this->kmt();
        $service = app(OperationalRecordsService::class);
        $schedule = $service->createSchedule($kmt, [
            'record_type' => 'Equipment calibration evidence', 'retention_duration' => 3,
            'retention_unit' => 'years', 'trigger_event' => 'closure', 'legal_basis' => null,
            'disposition_method' => null, 'approval_authority' => null, 'is_active' => false,
        ]);
        $this->assertFalse($schedule->is_active);
        $record = $service->createRecord($kmt, ['record_type' => $schedule->record_type, 'title' => 'Calibration 001', 'retention_schedule_id' => $schedule->id]);
        $service->placeHold($record, $kmt, 'Investigation hold', 'INV-001');
        $this->assertSame('on_hold', $record->fresh()->status);

        try {
            $service->requestDisposition($record->fresh(), $kmt, 'Retention reached');
            $this->fail('A held operational record must not enter disposition.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertDatabaseMissing('disposition_requests', ['operational_record_id' => $record->id]);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'operational_record.hold_placed']);
    }

    public function test_kmt_receives_json_summary_reports(): void
    {
        $kmt = $this->kmt();
        $this->actingAs($kmt)->getJson('/api/reports/summary')
            ->assertOk()
            ->assertJsonStructure(['data' => ['documents', 'released_versions', 'distributions', 'migration_batches', 'operational_records', 'audit_events']]);
        $this->actingAs($kmt)->getJson('/api/reports/audit?limit=10')->assertOk()->assertJsonStructure(['data']);
    }

    public function test_a_hold_added_after_a_disposition_request_blocks_approval(): void
    {
        $kmt = $this->kmt();
        $schedule = RetentionSchedule::query()->create([
            'record_type' => 'Test record', 'retention_duration' => 1, 'retention_unit' => 'days',
            'trigger_event' => 'closure', 'disposition_method' => 'approved disposal', 'is_active' => true,
        ]);
        $record = app(OperationalRecordsService::class)->createRecord($kmt, [
            'record_type' => 'Test record', 'title' => 'Held after request', 'retention_schedule_id' => $schedule->id,
            'retention_triggered_on' => now()->subDays(4)->toDateString(),
        ]);
        $request = app(OperationalRecordsService::class)->requestDisposition($record, $kmt, 'Retention period elapsed.');
        app(OperationalRecordsService::class)->placeHold($record, $kmt, 'New investigation hold', null);

        try {
            app(OperationalRecordsService::class)->decide($request, $kmt, 'approved', 'Approve disposal.');
            $this->fail('A hold added after the request must block approval.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame('pending', $request->fresh()->status);
    }

    private function kmt(): User
    {
        $user = $this->user('kmt-records@example.test');
        $role = Role::query()->create(['code' => 'KMT', 'name' => 'Knowledge Management Team', 'is_active' => true]);
        $permission = Permission::query()->create(['code' => 'records.manage', 'description' => 'KMT manages operational records.']);
        $role->permissions()->attach($permission->id, ['scope_type' => 'institution']);
        $user->roleAssignments()->create(['role_id' => $role->id, 'work_scope' => 'Institutional records', 'valid_from' => now()->toDateString()]);
        return $user;
    }

    private function user(string $email): User
    {
        return User::query()->create(['name' => $email, 'email' => $email, 'password' => 'password', 'is_active' => true]);
    }
}
