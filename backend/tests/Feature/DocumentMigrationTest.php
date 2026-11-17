<?php

namespace Tests\Feature;

use App\Models\DocumentMigrationBatch;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentMigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DocumentMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_queue_requires_kmt_permission_and_authentication(): void
    {
        $this->getJson('/api/document-migrations')->assertUnauthorized();
        $admin = $this->user('admin@example.test');
        $admin->update(['is_system_admin' => true]);
        $this->actingAs($admin)->getJson('/api/document-migrations')->assertForbidden();
    }

    public function test_source_files_are_private_hashed_and_reconciliation_counts_verification(): void
    {
        Storage::fake('local');
        $actor = $this->kmtUser();
        $service = app(DocumentMigrationService::class);
        $batch = $service->createBatch($actor, 'ONP source test', 'Guideline archive');
        $items = $service->addFiles($batch, $actor, [UploadedFile::fake()->createWithContent('source.pdf', '%PDF-1.4 verified source')]);
        $duplicate = $service->addFiles($batch, $actor, [UploadedFile::fake()->createWithContent('copy.pdf', '%PDF-1.4 verified source')])[0];
        $this->assertSame('duplicate_candidate', $duplicate->verification_status);
        $this->assertSame('file_hash', $duplicate->duplicate_candidates[0]['type']);
        $item = $items[0];

        $this->assertSame('pending_verification', $item->verification_status);
        $this->assertSame(hash('sha256', '%PDF-1.4 verified source'), $item->sha256);
        Storage::disk('local')->assertExists($item->storage_key);
        $this->assertSame(1, $service->reconciliation($batch)['pending']);

        $mapped = $service->updateMapping($item, $actor, [
            'original_code' => 'ONP-QMS-POL-001', 'original_title' => 'Quality Policy', 'revision_label' => '1.0',
        ], ['document_type_id' => 1], 'Archive binder A');
        $this->assertSame('ONP-QMS-POL-001', $mapped->source_metadata['original_code']);
        $this->assertSame('1.0', $mapped->source_metadata['revision_label']);
        $this->assertSame('Archive binder A', $mapped->source_location);

        $verified = $service->verify($mapped, $actor, 'verified', 'Source reviewed; original authority details retained.');
        $this->assertSame('verified', $verified->verification_status);
        $this->assertSame(1, $service->reconciliation($batch->fresh())['verified']);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'migration.source_uploaded']);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'migration.item_verified', 'to_state' => 'verified']);
    }

    public function test_duplicate_candidate_requires_resolution_note_for_verified_status(): void
    {
        $actor = $this->kmtUser();
        $batch = DocumentMigrationBatch::query()->create(['name' => 'Duplicate test', 'status' => 'open', 'created_by_user_id' => $actor->id]);
        $item = $batch->items()->create([
            'original_filename' => 'duplicate.pdf', 'storage_key' => 'document-migrations/duplicate.pdf',
            'media_type' => 'application/pdf', 'byte_size' => 10, 'sha256' => str_repeat('a', 64),
            'source_metadata' => [], 'duplicate_candidates' => [['type' => 'file_hash', 'label' => 'existing.pdf']],
            'verification_status' => 'duplicate_candidate', 'uploaded_by_user_id' => $actor->id,
        ]);

        try {
            app(DocumentMigrationService::class)->verify($item, $actor, 'verified', null);
            $this->fail('Duplicate candidate resolution requires an explanation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('verification_notes', $exception->errors());
        }
    }

    public function test_verified_import_registers_as_draft_without_approval_or_release(): void
    {
        Storage::fake('local');
        $actor = $this->kmtUser();
        $batch = app(DocumentMigrationService::class)->createBatch($actor, 'Register draft test', null);
        $item = app(DocumentMigrationService::class)->addFiles($batch, $actor, [UploadedFile::fake()->createWithContent('source.pdf', '%PDF-1.4 preserved')])[0];
        $item = app(DocumentMigrationService::class)->verify($item, $actor, 'verified', 'Metadata checked against source.');
        $type = DocumentType::query()->create(['code' => 'POL', 'name' => 'Policy', 'is_active' => true]);
        $unit = OrganizationalUnit::query()->create(['name' => 'Operations', 'unit_type' => 'service', 'is_active' => true]);

        $document = app(DocumentMigrationService::class)->registerVerified($item, $actor, [
            'title' => 'Imported policy', 'document_type_id' => $type->id, 'owning_unit_id' => $unit->id,
            'owner_user_id' => $actor->id, 'revision_label' => 'Rev A',
        ]);

        $this->assertSame('pending_code', $document->status);
        $this->assertSame('draft', $document->versions->first()->status);
        $this->assertSame('Rev A', $document->versions->first()->revision_label);
        $this->assertSame($item->id, $item->fresh()->id);
        $this->assertSame($document->id, $item->fresh()->registered_document_id);
        $this->assertSame($type->id, $item->fresh()->mapped_metadata['document_type_id']);
        $this->assertDatabaseMissing('audit_events', ['document_id' => $document->id, 'event_type' => 'document.released']);
        Storage::disk('local')->assertExists($document->versions->first()->files->first()->storage_key);
    }

    private function kmtUser(): User
    {
        $user = $this->user('kmt-migration@example.test');
        $role = Role::query()->create(['code' => 'KMT', 'name' => 'Knowledge Management Team', 'is_active' => true]);
        $permission = Permission::query()->create(['code' => 'document.register', 'description' => 'KMT registers documents.']);
        $role->permissions()->attach($permission->id, ['scope_type' => 'institution']);
        $user->roleAssignments()->create(['role_id' => $role->id, 'work_scope' => 'Institutional register', 'valid_from' => now()->toDateString()]);
        return $user;
    }

    private function user(string $email): User
    {
        return User::query()->create(['name' => $email, 'email' => $email, 'password' => 'password', 'is_active' => true]);
    }
}
