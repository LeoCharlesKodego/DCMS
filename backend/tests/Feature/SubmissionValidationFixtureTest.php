<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\SubmissionValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionValidationFixtureTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_six_supplied_onp_docx_fixtures_get_safe_recorded_check_runs(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $unit = OrganizationalUnit::query()->create(['name' => 'Fixture test unit', 'unit_type' => 'test', 'is_active' => true]);
        $type = DocumentType::query()->create(['code' => 'TEST', 'name' => 'Fixture test type', 'is_active' => true]);
        $fixtures = [
            '1_-General-Guidelines-for-Knowledge-Management_Cod.docx',
            '2_-KMT_2__Specific_Guidelines_for_Document_Coding_.docx',
            'Annex_1-3_2026.docx',
            'Annex-4_-Revised-Format-for-Guidelines-or-Manual_t.docx',
            'Annex-5_-Revised-Format-for-Specific-Guidelines_to.docx',
            'Annex-6_-Revised-Format-for-Policies-and-Procedure.docx',
        ];

        foreach ($fixtures as $name) {
            $path = base_path('../DCMS_AI_Development_Pack/reference/source_originals/'.$name);
            $this->assertFileExists($path, $name);
            $bytes = file_get_contents($path);
            $this->assertIsString($bytes);
            $document = Document::query()->create([
                'document_type_id' => $type->id, 'owner_user_id' => $user->id, 'owning_unit_id' => $unit->id,
                'created_by_user_id' => $user->id, 'title' => 'Fixture: '.$name,
                'current_revision_label' => '1.0', 'status' => 'pending_code',
            ]);
            $version = DocumentVersion::query()->create([
                'document_id' => $document->id, 'sequence_no' => 1, 'revision_label' => '1.0',
                'status' => 'draft', 'created_by_user_id' => $user->id,
            ]);
            $key = 'fixtures/'.$name;
            Storage::disk('local')->put($key, $bytes);
            $file = DocumentFile::query()->create([
                'document_version_id' => $version->id, 'original_filename' => $name,
                'storage_key' => $key, 'media_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'byte_size' => strlen($bytes), 'sha256' => hash('sha256', $bytes),
                'uploaded_by_user_id' => $user->id, 'uploaded_at' => now(),
            ]);

            $run = app(SubmissionValidationService::class)->validate($file, $user);
            $this->assertDatabaseHas('submission_validation_runs', ['document_file_id' => $file->id, 'run_number' => 1]);
            $titleFinding = collect($run->findings)->firstWhere('rule', 'title_14pt');
            $this->assertNotNull($titleFinding, $name.' must record the title-size result.');
            if ($titleFinding['result'] === 'not_checked') {
                $this->assertSame('not checked, KMT to verify manually', $titleFinding['detail']);
            }
        }
    }
}
