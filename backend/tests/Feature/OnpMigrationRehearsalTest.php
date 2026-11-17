<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentMigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OnpMigrationRehearsalTest extends TestCase
{
    use RefreshDatabase;

    public function test_onp_source_documents_rehearse_as_unverified_private_imports(): void
    {
        Storage::fake('local');
        $sourceDirectory = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'DCMS_AI_Development_Pack'.DIRECTORY_SEPARATOR.'reference'.DIRECTORY_SEPARATOR.'source_originals';
        $paths = glob($sourceDirectory.DIRECTORY_SEPARATOR.'*.docx') ?: [];
        sort($paths);
        $this->assertNotEmpty($paths, 'The approved ONP source documents must be present for the migration rehearsal.');
        $uploads = array_map(fn (string $path) => new UploadedFile($path, basename($path), null, UPLOAD_ERR_OK, true), $paths);
        $actor = $this->kmtUser();
        $migration = app(DocumentMigrationService::class);
        $batch = $migration->createBatch($actor, 'Phase 8 ONP source rehearsal', 'Supplied source_originals DOCX files; isolated test database/private disk.');
        $items = $migration->addFiles($batch, $actor, $uploads);

        $this->assertCount(count($paths), $items);
        foreach ($paths as $index => $path) {
            $item = $items[$index];
            $this->assertSame(basename($path), $item->original_filename);
            $this->assertSame(hash_file('sha256', $path), $item->sha256);
            $this->assertSame('pending_verification', $item->verification_status);
            Storage::disk('local')->assertExists($item->storage_key);
        }
        $summary = $migration->reconciliation($batch->fresh());
        $this->assertSame(count($paths), $summary['total']);
        $this->assertSame(count($paths), $summary['pending']);
        $this->assertSame(0, $summary['verified']);
    }

    private function kmtUser(): User
    {
        $user = User::query()->create(['name' => 'KMT Rehearsal', 'email' => 'kmt-rehearsal@example.test', 'password' => 'password', 'is_active' => true]);
        $role = Role::query()->create(['code' => 'KMT', 'name' => 'Knowledge Management Team', 'is_active' => true]);
        $permission = Permission::query()->create(['code' => 'document.register', 'description' => 'KMT registers documents.']);
        $role->permissions()->attach($permission->id, ['scope_type' => 'institution']);
        $user->roleAssignments()->create(['role_id' => $role->id, 'work_scope' => 'Institutional migration', 'valid_from' => now()->toDateString()]);
        return $user;
    }
}
