<?php

namespace Database\Seeders;

use App\Models\DocumentCodeComponent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OnpCodeListSeeder extends Seeder
{
    public function run(): void
    {
        $sourcePath = dirname(base_path()).'/DCMS_AI_Development_Pack/reference/ONP_CODE_LISTS.md';
        if (! is_file($sourcePath)) {
            throw new RuntimeException('The official ONP code-list source file is missing: '.$sourcePath);
        }

        $rows = $this->parseSource(file($sourcePath, FILE_IGNORE_NEW_LINES));
        $normalizedCodes = array_map('strtoupper', array_column($rows, 'code'));
        $counts = array_count_values($normalizedCodes);
        $knownConflicts = ['ADM', 'KMT', 'HRP', 'HRPU'];
        foreach ($rows as &$row) {
            $flagsConflict = preg_match('/\b(DUP|CONF)\b|DUP-like/i', $row['source_flags'] ?? '') === 1;
            $row['is_conflicted'] = ($counts[strtoupper($row['code'])] ?? 0) > 1
                || $flagsConflict
                || in_array($row['code'], $knownConflicts, true);
            $row['is_active'] = ! $row['is_conflicted'];
        }
        unset($row);

        DB::transaction(function () use ($rows): void {
            $sourceKeys = [];
            foreach ($rows as $row) {
                if ($row['component_type'] === 'service') {
                    $sourceKeys[] = $row['source_key'];
                    DocumentCodeComponent::query()->updateOrCreate(
                        ['source_key' => $row['source_key']],
                        $row + ['parent_service_id' => null],
                    );
                }
            }

            foreach ($rows as $row) {
                if ($row['component_type'] === 'service') {
                    continue;
                }

                $parentServiceId = null;
                if ($row['parent_service_code'] !== null) {
                    $parentServiceId = DocumentCodeComponent::query()
                        ->where('component_type', 'service')
                        ->where('code', $row['parent_service_code'])
                        ->value('id');
                    if ($parentServiceId === null) {
                        throw new RuntimeException('Missing official parent service code '.$row['parent_service_code'].'.');
                    }
                }

                $sourceKeys[] = $row['source_key'];
                unset($row['parent_service_code']);
                DocumentCodeComponent::query()->updateOrCreate(
                    ['source_key' => $row['source_key']],
                    $row + ['parent_service_id' => $parentServiceId],
                );
            }

            DocumentCodeComponent::query()
                ->whereNotIn('source_key', $sourceKeys)
                ->update(['is_conflicted' => true, 'is_active' => false]);
        });
    }

    /** @param list<string> $lines
     *  @return list<array<string, mixed>>
     */
    private function parseSource(array $lines): array
    {
        $componentType = null;
        $sourceGroup = '';
        $parentServiceCode = null;
        $rows = [];

        foreach ($lines as $lineNumber => $line) {
            $heading = trim($line);
            if (preg_match('/^## 1\. Service/i', $heading)) {
                $componentType = 'service';
                $sourceGroup = $heading;
                $parentServiceCode = null;
                continue;
            }
            if (preg_match('/^## 2\. Document type/i', $heading)) {
                $componentType = 'document_type';
                $sourceGroup = $heading;
                $parentServiceCode = null;
                continue;
            }
            if (preg_match('/^## 3\./i', $heading) || preg_match('/^## 4\. Hospital committees/i', $heading)) {
                $componentType = null;
                continue;
            }
            if (preg_match('/^## 5\./i', $heading)) {
                break;
            }
            if (preg_match('/^### (3\.|4\.)/i', $heading)) {
                $componentType = 'section_unit';
                $sourceGroup = preg_replace('/^###\s+/', '', $heading) ?? $heading;
                $parentServiceCode = $this->parentServiceCode($sourceGroup);
                continue;
            }

            if ($componentType === null || ! str_starts_with($heading, '|')) {
                continue;
            }

            $cells = array_map('trim', explode('|', trim($heading, '|')));
            if (count($cells) < 2 || strcasecmp($cells[0], 'Code') === 0 || preg_match('/^-+$/', $cells[0])) {
                continue;
            }

            $code = $cells[0];
            $name = $cells[1];
            if ($code === '' || $name === '') {
                continue;
            }

            $sourceFlags = $componentType === 'section_unit' ? ($cells[2] ?? '') : '';
            $sourceFlags = $sourceFlags === '' ? null : $sourceFlags;
            $sourceKey = hash('sha256', implode('|', [
                $componentType,
                $sourceGroup,
                (string) $lineNumber,
                $code,
                $name,
            ]));

            $rows[] = [
                'source_key' => $sourceKey,
                'parent_service_code' => $componentType === 'section_unit' ? $parentServiceCode : null,
                'component_type' => $componentType,
                'code' => $code,
                'name' => $name,
                'source_group' => $sourceGroup,
                'source_flags' => $sourceFlags,
                'is_conflicted' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows === []) {
            throw new RuntimeException('No code-list entries were found in the official ONP source file.');
        }

        return $rows;
    }

    private function parentServiceCode(string $sourceGroup): string
    {
        return match (true) {
            str_contains($sourceGroup, 'Office of the Medical Center Chief'),
            str_contains($sourceGroup, 'Under the Medical Center Chief') => 'MCC',
            str_contains($sourceGroup, 'Medical Service') => 'MED',
            str_contains($sourceGroup, 'Nursing Service') => 'NUR',
            str_contains($sourceGroup, 'Hospital Operations') => 'HOP',
            str_contains($sourceGroup, 'Allied Health Professional') => 'AHP',
            str_contains($sourceGroup, 'Finance Service') => 'FIN',
            str_contains($sourceGroup, 'Quality Management System') => 'QMS',
            str_contains($sourceGroup, 'Chief of Medical Professional Staff') => 'COM',
            default => throw new RuntimeException('No official parent service mapping exists for source group: '.$sourceGroup),
        };
    }
}
