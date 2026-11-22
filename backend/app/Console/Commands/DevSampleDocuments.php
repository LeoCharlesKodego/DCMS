<?php

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\DocumentCodeComponent;
use App\Models\DocumentDistribution;
use App\Models\DocumentFile;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\DistributionRecipient;
use App\Models\DistributionTarget;
use App\Models\MasterCodeRegisterEntry;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\SubmissionValidationRun;
use App\Models\User;
use App\Models\WorkflowDecision;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStageInstance;
use App\Models\WorkflowTask;
use App\Services\DocumentAuditService;
use App\Services\DocumentCodeNumberingService;
use App\Services\DocumentDistributionService;
use App\Services\DocumentLifecycleService;
use App\Services\DocumentVersionStorageService;
use App\Services\WorkflowDecisionService;
use Database\Seeders\LocalPhase8DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Builds clearly fictional sample documents across the lifecycle in the
 * disposable local SQLite database so the interface and browser tests have
 * realistic records to work with. It refuses production and never runs from
 * DatabaseSeeder.
 */
class DevSampleDocuments extends Command
{
    protected $signature = 'dcms:dev-sample-documents';

    protected $description = 'Create fictional sample documents across lifecycle states in the local SQLite development database';

    private const TITLE_PREFIX = 'Sample — ';

    public function handle(
        DocumentAuditService $audit,
        DocumentCodeNumberingService $numbering,
        DocumentVersionStorageService $storage,
        DocumentLifecycleService $lifecycle,
        WorkflowDecisionService $decisions,
        DocumentDistributionService $distributions,
    ): int {
        if (app()->environment('production')) {
            $this->components->error('Refused: this command only writes fictional sample data outside production.');

            return self::FAILURE;
        }

        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->components->error('Refused: this command only writes to the local SQLite development database.');

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => LocalPhase8DemoSeeder::class, '--force' => true]);

        $this->removePreviousSamples();

        $unitId = (int) OrganizationalUnit::query()->where('name', 'Sample Operations Unit')->value('id');
        $typeId = (int) DocumentType::query()->where('code', 'PROC')->value('id');
        $components = [
            'service_component_id' => (int) DocumentCodeComponent::query()->where('source_key', 'LOCAL-DEMO-SERVICE')->value('id'),
            'section_component_id' => (int) DocumentCodeComponent::query()->where('source_key', 'LOCAL-DEMO-SECTION')->value('id'),
            'unit_component_id' => null,
            'document_type_component_id' => (int) DocumentCodeComponent::query()->where('source_key', 'LOCAL-DEMO-DOCTYPE')->value('id'),
        ];

        $owner = User::query()->where('email', 'process.owner@example.test')->firstOrFail();
        $kmt = User::query()->where('email', 'kmt@example.test')->firstOrFail();
        $kmtRelease = User::query()->where('email', 'kmt.release@example.test')->firstOrFail();
        $divisionHead = User::query()->where('email', 'division.head@example.test')->firstOrFail();
        $mcc = User::query()->where('email', 'mcc@example.test')->firstOrFail();
        $staff = User::query()->where('email', 'staff.recipient@example.test')->firstOrFail();

        $makeDocument = function (string $title) use ($audit, $owner, $typeId, $unitId): Document {
            $document = Document::query()->create([
                'document_type_id' => $typeId,
                'category_id' => null,
                'owner_user_id' => $owner->id,
                'owning_unit_id' => $unitId,
                'created_by_user_id' => $owner->id,
                'title' => self::TITLE_PREFIX.$title,
                'current_revision_label' => '1.0',
                'status' => 'pending_code',
                'retention_years' => 7,
                'remarks' => 'Fictional local sample data; not an institutional record.',
            ]);
            $audit->record('document', $document->id, $document, $owner, 'document.created', null, 'pending_code');

            return $document;
        };

        $assignCode = function (Document $document) use ($audit, $components, $kmt, $numbering): string {
            $numbering->assign($document, $kmt, $components, $audit);
            $document->refresh();

            return (string) $document->masterCodeRegisterEntry()->value('base_code');
        };

        $uploadDraft = function (Document $document, string $code) use ($audit, $owner, $storage): DocumentVersion {
            $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dcms-sample-'.bin2hex(random_bytes(6)).'.pdf';
            file_put_contents($path, $this->fictionalPdf($code));
            try {
                $upload = new UploadedFile($path, $code.'.pdf', 'application/pdf', null, true);

                return $storage->createVersion($document, $owner, [], [$upload], $audit);
            } finally {
                @unlink($path);
            }
        };

        $pendingTask = function (DocumentVersion $version, string $actionType): WorkflowTask {
            $instanceId = $version->workflowInstance()->value('id');

            return WorkflowTask::query()
                ->whereHas('stageInstance', fn ($query) => $query->where('workflow_instance_id', $instanceId))
                ->where('status', 'pending')
                ->get()
                ->first(fn (WorkflowTask $task): bool => $task->stageInstance->templateStage->action_type === $actionType)
                ?? throw new \RuntimeException('No pending '.$actionType.' task was created for the sample workflow.');
        };

        // 1. Awaiting an official code: only the assign-code form is available.
        $pendingCode = $makeDocument('Awaiting official code');

        // 2. Draft revision with a code and a checked file: replace/upload forms.
        $draft = $makeDocument('Draft procedure');
        $draftCode = $assignCode($draft);
        $draftVersion = $uploadDraft($draft, $draftCode);

        // 3. Returned by KMT verification: returned forms plus a resubmit path.
        $returned = $makeDocument('Returned procedure');
        $returnedCode = $assignCode($returned);
        $returnedVersion = $uploadDraft($returned, $returnedCode);
        $returnedVersion = $lifecycle->act($returnedVersion, $owner, 'submit');
        $decisions->decide(
            $pendingTask($returnedVersion, 'verification'),
            $kmt,
            'returned_for_revision',
            'Sample: fictional submission returned so the correction form can be exercised.',
        );

        // 4. Released with an exact-version distribution requiring acknowledgment.
        $released = $makeDocument('Released procedure');
        $releasedCode = $assignCode($released);
        $releasedVersion = $uploadDraft($released, $releasedCode);
        $releasedVersion = $lifecycle->act($releasedVersion, $owner, 'submit');
        $decisions->decide($pendingTask($releasedVersion, 'verification'), $kmt, 'verified', 'Sample: fictional submission checks reviewed.');
        $decisions->decide($pendingTask($releasedVersion->refresh(), 'review'), $divisionHead, 'reviewed', 'Sample: fictional Division Head review recorded.');
        $decisions->decide($pendingTask($releasedVersion->refresh(), 'approval'), $mcc, 'approved', 'Sample: fictional final approval recorded.');
        $today = now()->toDateString();
        $lifecycle->act($releasedVersion->refresh(), $owner, 'propose_effective_date', null, $today);
        $lifecycle->act($releasedVersion->refresh(), $kmtRelease, 'confirm_effective_date', null, $today);
        $releasedVersion = $lifecycle->act($releasedVersion->refresh(), $kmtRelease, 'release');
        $distributions->issue($releasedVersion->refresh(), $kmtRelease, [
            'targets' => [['target_type' => 'user', 'target_id' => $staff->id]],
            'acknowledgment_required' => true,
            'due_at' => now()->addDays(7)->toDateString(),
        ]);

        $this->grantLocalOwnerScope($unitId);

        $this->table(['Sample document', 'Code', 'Status'], [
            [$pendingCode->title, 'Awaiting code', $pendingCode->status],
            [$draft->title, (string) $draft->fresh()->masterCodeRegisterEntry()->value('base_code'), (string) $draftVersion->fresh()->status],
            [$returned->title, (string) $returned->fresh()->masterCodeRegisterEntry()->value('base_code'), (string) $returnedVersion->fresh()->status],
            [$released->title, (string) $released->fresh()->masterCodeRegisterEntry()->value('base_code'), (string) $releasedVersion->fresh()->status],
        ]);
        $this->line('Fictional sample documents only. They live in database/local-dev.sqlite and are never seeded in production.');

        return self::SUCCESS;
    }

    /**
     * Give the disposable local sign-in account a Process Owner scope so the
     * upload, replace, and submit forms can be exercised locally.
     */
    private function grantLocalOwnerScope(int $unitId): void
    {
        $email = mb_strtolower(trim((string) (getenv('DCMS_DEV_EMAIL') ?: '')), 'UTF-8');
        if ($email === '') {
            return;
        }

        $user = User::query()->where('email', $email)->first();
        $role = Role::query()->where('code', 'PROCESS_OWNER')->first();
        if (! $user || ! $role) {
            return;
        }

        $user->roleAssignments()->updateOrCreate(
            ['role_id' => $role->id],
            [
                'organizational_unit_id' => $unitId,
                'work_scope' => 'Local sample documents',
                'valid_from' => now()->toDateString(),
                'valid_until' => null,
            ],
        );
    }

    private function removePreviousSamples(): void
    {
        $ids = Document::query()->where('title', 'like', self::TITLE_PREFIX.'%')->pluck('id')->all();
        if ($ids === []) {
            return;
        }

        $versions = DocumentVersion::query()->whereIn('document_id', $ids)->pluck('id')->all();
        $files = DocumentFile::query()->whereIn('document_version_id', $versions)->pluck('id')->all();
        $instances = WorkflowInstance::query()->whereIn('document_version_id', $versions)->pluck('id')->all();
        $stages = WorkflowStageInstance::query()->whereIn('workflow_instance_id', $instances)->pluck('id')->all();
        $tasks = WorkflowTask::query()->whereIn('workflow_stage_instance_id', $stages)->pluck('id')->all();
        $distributions = DocumentDistribution::query()->whereIn('document_version_id', $versions)->pluck('id')->all();
        $recipients = DistributionRecipient::query()->whereIn('document_distribution_id', $distributions)->pluck('id')->all();

        DB::table('distribution_recipient_sources')->whereIn('distribution_recipient_id', $recipients)->delete();
        DB::table('user_notifications')->where('target_type', 'distribution_recipient')->whereIn('target_id', $recipients)->delete();
        DistributionRecipient::query()->whereIn('id', $recipients)->delete();
        DistributionTarget::query()->whereIn('document_distribution_id', $distributions)->delete();
        DocumentDistribution::query()->whereIn('id', $distributions)->delete();
        WorkflowDecision::query()->whereIn('workflow_task_id', $tasks)->delete();
        WorkflowTask::query()->whereIn('id', $tasks)->delete();
        WorkflowStageInstance::query()->whereIn('id', $stages)->delete();
        WorkflowInstance::query()->whereIn('id', $instances)->delete();
        SubmissionValidationRun::query()->whereIn('document_file_id', $files)->delete();
        DocumentFile::query()->whereIn('id', $files)->delete();
        DocumentVersion::query()->whereIn('id', $versions)->delete();
        MasterCodeRegisterEntry::query()->whereIn('document_id', $ids)->delete();
        AuditEvent::query()->whereIn('document_id', $ids)->delete();
        Document::query()->whereIn('id', $ids)->delete();

        foreach ($ids as $id) {
            Storage::disk('local')->deleteDirectory('documents/'.$id);
        }
    }

    /** Builds a minimal, valid PDF whose only extractable text is one code. */
    private function fictionalPdf(string $code): string
    {
        $stream = 'BT /F1 12 Tf 72 720 Td (Fictional local sample procedure '.$code.') Tj ET';

        return implode("\n", [
            '%PDF-1.4',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            'endobj',
            '4 0 obj',
            '<< /Length '.strlen($stream).' >>',
            'stream',
            $stream,
            'endstream',
            'endobj',
            '5 0 obj',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R /Size 6 >>',
            '%%EOF',
            '',
        ]);
    }
}
