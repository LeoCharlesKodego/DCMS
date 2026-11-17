<?php

namespace App\Services;

use App\Models\DocumentFile;
use App\Models\DocumentVersion;
use App\Models\SubmissionValidationRun;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class SubmissionValidationService
{
    private const MAX_BYTES = 52428800;
    private const MAX_UNCOMPRESSED = 209715200;
    private const MAX_RATIO = 100;
    private const MAX_ENTRIES = 10000;

    public function validate(DocumentFile $file, ?User $actor = null): SubmissionValidationRun
    {
        $version = $file->version()->with('document.masterCodeRegisterEntry')->firstOrFail();
        $path = Storage::disk('local')->path($file->storage_key);
        $findings = [];
        $code = null;
        $codeSource = null;
        $titleResult = 'not_checked';
        $ext = strtolower(pathinfo($file->original_filename, PATHINFO_EXTENSION));
        $start = hrtime(true);
        $bytes = filesize($path);

        $this->finding($findings, 'size', $bytes !== false && $bytes <= self::MAX_BYTES ? 'pass' : 'block', $bytes);
        if ($bytes === false || $bytes > self::MAX_BYTES) {
            $this->finding($findings, 'title_14pt', 'not_checked', 'not checked, KMT to verify manually');
            return $this->saveRun($file, $actor, $findings, null, null);
        }

        $signature = file_get_contents($path, false, null, 0, 16);
        $expected = in_array($ext, ['jpg', 'jpeg'], true) ? 'jpeg' : $ext;
        $detected = $this->detectType((string) $signature);
        $validSignature = $expected === 'docx' || $expected === 'xlsx'
            ? $detected === 'zip-office'
            : $expected === $detected;
        $this->finding($findings, 'signature_type', $validSignature ? 'pass' : 'block', ['expected' => $expected, 'detected' => $detected]);
        if (! $validSignature) {
            $this->finding($findings, 'title_14pt', 'not_checked', 'not checked, KMT to verify manually');
            return $this->saveRun($file, $actor, $findings, null, null);
        }

        try {
            $text = match ($expected) {
                'docx' => $this->readOfficeText($path, 'word/document.xml', $start),
                'xlsx' => $this->readXlsxText($path, $start),
                'pdf' => $this->readPdfText($path, $start),
                default => '',
            };
            if ($expected === 'docx') $titleResult = $this->docxTitleFinding($path, $start);
            if ($expected === 'pdf' && trim($text) === '') {
                $this->finding($findings, 'pdf_scanned_manual_entry', 'manual', 'PDF has no extractable text layer; no OCR performed.');
            }
            if (in_array($expected, ['docx', 'xlsx', 'pdf'], true) && trim($text) !== '') {
                preg_match_all('/(?<![A-Z0-9])([A-Z0-9]{1,}(?:-[A-Z0-9]{1,}){2,})(?![A-Z0-9])/i', $text, $matches, PREG_OFFSET_CAPTURE);
                $candidates = array_values(array_unique(array_map(fn ($match) => strtoupper($match[0]), $matches[1] ?? [])));
                if (count($candidates) === 1) {
                    $code = $candidates[0];
                    $codeSource = 'extracted';
                } elseif (count($candidates) > 1) {
                    $this->finding($findings, 'multiple_code_candidates', 'warning', $candidates);
                }
                $this->finding($findings, 'code_candidate_locations', 'info', array_map(fn ($match) => ['value' => strtoupper($match[0]), 'text_offset' => (int) $match[1]], $matches[1] ?? []));
            }
            if (in_array($expected, ['jpeg', 'png'], true) || ($expected === 'pdf' && trim($text) === '')) {
                $this->finding($findings, 'code_extraction', 'manual', 'KMT manual code entry required.');
            }

            $this->finding($findings, 'readability', 'pass', 'Parser completed.');
            $sameHash = DocumentFile::query()->where('sha256', $file->sha256)->whereKeyNot($file->id)->exists();
            $this->finding($findings, 'exact_file_duplicate', $sameHash ? 'warning' : 'pass', $sameHash ? 'Matching SHA-256 exists on another upload.' : null);

            $assigned = $version->document->masterCodeRegisterEntry?->base_code;
            if ($assigned !== null && (in_array($expected, ['jpeg', 'png'], true) || ($expected === 'pdf' && trim($text) === ''))) {
                $codeSource = 'manual';
                $code = strtoupper($assigned);
            }
            if ($code !== null) {
                $otherOwner = \App\Models\MasterCodeRegisterEntry::query()->where('base_code', $code)->where('document_id', '<>', $version->document_id)->exists();
                $this->finding($findings, 'code_identity_conflict', $otherOwner ? 'block' : 'pass', $otherOwner ? 'Code belongs to another document.' : null);
                if ($assigned !== null) $this->finding($findings, 'assigned_code_match', $code === strtoupper($assigned) ? 'pass' : 'warning', ['extracted' => $code, 'assigned' => $assigned]);
                if (preg_match('/[A-Z0-9]+(?:-[A-Z0-9]+){3,}-[0-9]{3}/i', $file->original_filename, $filenameMatch)) {
                    $this->finding($findings, 'filename_code_mismatch', strtoupper($filenameMatch[0]) === $code ? 'pass' : 'warning', ['file_code' => $code, 'filename_code' => strtoupper($filenameMatch[0])]);
                }
                $formatValid = (bool) preg_match('/^(?:[A-Z0-9]{1,}-){3,}[0-9]{3}$/', $code);
                $this->finding($findings, 'code_format', $formatValid ? 'pass' : 'warning', $code);
                $segments = explode('-', $code);
                $officialParts = array_slice($segments, 1, -1);
                $conflictedPart = \App\Models\DocumentCodeComponent::query()->whereIn('code', $officialParts)->where('is_conflicted', true)->exists();
                $validParts = \App\Models\DocumentCodeComponent::query()->whereIn('code', $officialParts)->where('is_active', true)->where('is_conflicted', false)->pluck('code')->unique()->all();
                $unknownParts = array_values(array_diff($officialParts, $validParts));
                $this->finding($findings, 'approved_code_list_validity', $unknownParts === [] ? 'pass' : 'warning', $unknownParts === [] ? null : ['unrecognized_components' => $unknownParts]);
                $this->finding($findings, 'official_code_list_conflict', $conflictedPart ? 'block' : 'pass', $conflictedPart ? 'Candidate includes a conflicted official component.' : null);
                $this->finding($findings, 'code_global_uniqueness', $otherOwner ? 'block' : 'pass', $otherOwner ? 'Code belongs to another document.' : 'Same-document revision reuse is allowed.');
            } elseif ($assigned !== null && ! in_array($expected, ['jpeg', 'png'], true) && ! ($expected === 'pdf' && trim($text) === '')) {
                $this->finding($findings, 'assigned_code_match', 'warning', 'No code candidate was extracted; file must be corrected to show the KMT-assigned code.');
            }
            if ($assigned === null) {
                $this->finding($findings, 'code_assignment', 'pending', 'awaiting KMT code assignment');
            }
        } catch (RuntimeException $exception) {
            $this->finding($findings, 'parser_safety', 'block', $exception->getMessage());
        }
        $this->finding($findings, 'title_14pt', $titleResult, $titleResult === 'not_checked' ? 'not checked, KMT to verify manually' : null);

        return $this->saveRun($file, $actor, $findings, $codeSource, $code);
    }

    private function readOfficeText(string $path, string $entryName, int $start): string
    {
        $zip = $this->openSafeZip($path, $start);
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = strtolower((string) $zip->getNameIndex($i));
                if (str_contains($name, 'vbaproject') || str_contains($name, 'external')) throw new RuntimeException('Macros or external links are not accepted or followed.');
                if (str_ends_with($name, '.rels')) {
                    $rels = (string) $zip->getFromIndex($i);
                    if (preg_match('/TargetMode\s*=\s*["\']External["\']/i', $rels)) throw new RuntimeException('External relationships are not followed.');
                }
            }
            $names = [$entryName];
            if (str_starts_with($entryName, 'word/')) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = (string) $zip->getNameIndex($i);
                    if (preg_match('#^word/(?:header|footer)[^/]*\.xml$#i', $name)) $names[] = $name;
                }
            }
            $output = '';
            foreach (array_unique($names) as $name) {
                $xml = $zip->getFromName($name);
                if (! is_string($xml)) throw new RuntimeException('Required Office XML part is missing.');
                $xmlReader = new \DOMDocument();
                if (! $xmlReader->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) throw new RuntimeException('Malformed Office XML.');
                $output .= ' '.$xmlReader->textContent;
            }
            return $output;
        } finally { $zip->close(); }
    }

    private function readXlsxText(string $path, int $start): string
    {
        $zip = $this->openSafeZip($path, $start);
        try {
            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) $names[] = $zip->getNameIndex($i);
            foreach ($names as $name) {
                if (str_contains(strtolower((string) $name), 'vbaproject') || str_contains(strtolower((string) $name), 'external')) throw new RuntimeException('Macros or external workbook links are not accepted.');
                if (str_ends_with(strtolower((string) $name), '.rels') && preg_match('/TargetMode\s*=\s*["\']External["\']/i', (string) $zip->getFromName($name))) throw new RuntimeException('External relationships are not followed.');
            }
            $shared = [];
            if ($sharedXml = $zip->getFromName('xl/sharedStrings.xml')) {
                $sharedDoc = new \DOMDocument();
                if (! $sharedDoc->loadXML($sharedXml, LIBXML_NONET | LIBXML_NOBLANKS)) throw new RuntimeException('Malformed shared strings.');
                $sharedXpath = new \DOMXPath($sharedDoc);
                $sharedXpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                foreach ($sharedXpath->query('//x:si') as $index => $item) $shared[$index] = $item->textContent;
            }
            $output = '';
            foreach ($names as $name) {
                if (! preg_match('#^xl/worksheets/[^/]+\.xml$#', (string) $name)) continue;
                $xml = $zip->getFromName($name);
                if (! is_string($xml)) continue;
                $doc = new \DOMDocument();
                if (! $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) throw new RuntimeException('Malformed workbook XML.');
                $xpath = new \DOMXPath($doc);
                $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                foreach ($xpath->query('//x:c') as $cell) {
                    $type = $cell->getAttribute('t');
                    if ($type === 's') {
                        $index = (int) ($xpath->query('./x:v', $cell)->item(0)?->textContent ?? -1);
                        $output .= ' '.($shared[$index] ?? '');
                    } elseif ($type === 'inlineStr') {
                        $output .= ' '.($xpath->query('./x:is', $cell)->item(0)?->textContent ?? '');
                    } else {
                        $output .= ' '.($xpath->query('./x:v', $cell)->item(0)?->textContent ?? '');
                    }
                }
            }
            return $output;
        } finally { $zip->close(); }
    }

    private function readPdfText(string $path, int $start): string
    {
        $bytes = file_get_contents($path);
        if (! is_string($bytes) || ! str_starts_with($bytes, '%PDF-')) throw new RuntimeException('Unreadable PDF file.');
        if (preg_match('/\/Encrypt\b/', $bytes)) throw new RuntimeException('Encrypted PDFs are not accepted.');
        preg_match_all('/\(((?:\\\\.|[^\\\\)])*)\)\s*Tj/s', $bytes, $matches);
        $text = implode(' ', array_map('stripcslashes', $matches[1] ?? []));
        if (hrtime(true) - $start > 30_000_000_000) throw new RuntimeException('Parser time limit exceeded.');
        return $text;
    }

    private function docxTitleFinding(string $path, int $start): string
    {
        $zip = $this->openSafeZip($path, $start);
        try {
            $xml = $zip->getFromName('word/document.xml') ?: '';
            $stylesXml = $zip->getFromName('word/styles.xml') ?: '';
            $doc = new \DOMDocument();
            if (! $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) return 'not_checked';
            $xpath = new \DOMXPath($doc);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $styles = null;
            if ($stylesXml !== '') {
                $styles = new \DOMDocument();
                if (! $styles->loadXML($stylesXml, LIBXML_NONET | LIBXML_NOBLANKS)) $styles = null;
            }
            foreach ($xpath->query('//w:p') as $paragraph) {
                $text = $paragraph->textContent;
                if (! preg_match('/Document\s+Title\s*:/i', $text)) continue;
                $cell = $xpath->query('ancestor::w:tc[1]', $paragraph)->item(0);
                if (! $cell) return 'not_checked';
                $blockParagraphs = iterator_to_array($xpath->query('.//w:p', $cell));
                $labelIndex = 0;
                foreach ($blockParagraphs as $index => $candidate) if ($candidate->isSameNode($paragraph)) $labelIndex = $index;
                $titleParagraph = $paragraph;
                if (! preg_match('/Document\s+Title\s*:\s*\S/i', $paragraph->textContent)) {
                    $titleParagraph = null;
                    foreach (array_slice($blockParagraphs, $labelIndex + 1) as $candidate) {
                        if (trim($candidate->textContent) !== '') { $titleParagraph = $candidate; break; }
                    }
                }
                if (! $titleParagraph) return 'not_checked';
                $afterLabel = ! $titleParagraph->isSameNode($paragraph);
                foreach ($xpath->query('.//w:r', $titleParagraph) as $run) {
                    $runText = trim($run->textContent);
                    if (! $afterLabel) {
                        if (! preg_match('/Document\s+Title\s*:\s*(.*)/i', $runText, $labelMatch)) continue;
                        $runText = trim($labelMatch[1]);
                        $afterLabel = true;
                    }
                    if ($runText === '') continue;
                    $size = $this->docxEffectiveFontSize($xpath, $styles, $run, $titleParagraph);
                    if ($size !== null) {
                        $required = (int) app(Phase4BSettingService::class)->get('title_size_pt') * 2;
                        return ((int) $size >= $required) ? 'pass' : 'warning';
                    }
                }
                return 'not_checked';
            }
            return 'not_checked';
        } finally { $zip->close(); }
    }

    private function docxEffectiveFontSize(\DOMXPath $documentXPath, ?\DOMDocument $styles, \DOMElement $run, \DOMElement $paragraph): ?string
    {
        $directRun = $documentXPath->query('./w:rPr/w:sz/@w:val', $run)->item(0)?->nodeValue;
        if ($directRun !== null) return $directRun;

        $runStyle = $documentXPath->query('./w:rPr/w:rStyle/@w:val', $run)->item(0)?->nodeValue;
        $characterStyleSize = $this->docxStyleFontSize($styles, $runStyle, 'character');
        if ($characterStyleSize !== null) return $characterStyleSize;

        $paragraphDirect = $documentXPath->query('./w:pPr/w:rPr/w:sz/@w:val', $paragraph)->item(0)?->nodeValue;
        if ($paragraphDirect !== null) return $paragraphDirect;

        $paragraphStyle = $documentXPath->query('./w:pPr/w:pStyle/@w:val', $paragraph)->item(0)?->nodeValue;
        $paragraphStyleSize = $this->docxStyleFontSize($styles, $paragraphStyle, 'paragraph');
        if ($paragraphStyleSize !== null) return $paragraphStyleSize;

        if ($styles) {
            $stylesXPath = new \DOMXPath($styles);
            $stylesXPath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            return $stylesXPath->query('/w:styles/w:docDefaults/w:rPrDefault/w:rPr/w:sz/@w:val')->item(0)?->nodeValue;
        }
        return null;
    }

    private function docxStyleFontSize(?\DOMDocument $styles, ?string $styleId, string $type): ?string
    {
        if (! $styles) return null;
        $xpath = new \DOMXPath($styles);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        if ($styleId === null || $styleId === '') {
            $styleId = $xpath->query('/w:styles/w:style[@w:type="'.$type.'"][@w:default="1"]/@w:styleId')->item(0)?->nodeValue;
        }
        $visited = [];
        while ($styleId !== null && $styleId !== '' && ! isset($visited[$styleId])) {
            $visited[$styleId] = true;
            $style = null;
            foreach ($xpath->query('/w:styles/w:style') as $candidate) {
                if ($candidate->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'styleId') === $styleId) {
                    $style = $candidate;
                    break;
                }
            }
            if (! $style) break;
            $size = $xpath->query('./w:rPr/w:sz/@w:val', $style)->item(0)?->nodeValue;
            if ($size !== null) return $size;
            $styleId = $xpath->query('./w:basedOn/@w:val', $style)->item(0)?->nodeValue;
        }
        return null;
    }

    private function openSafeZip(string $path, int $start): ZipArchive
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true || $zip->numFiles > self::MAX_ENTRIES) throw new RuntimeException('Office package is malformed or exceeds the package entry cap.');
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (! $stat) { $zip->close(); throw new RuntimeException('Malformed package entry.'); }
            $total += (int) $stat['size'];
            if ($total > self::MAX_UNCOMPRESSED || ((int) $stat['size'] > 0 && (int) $stat['comp_size'] === 0) || ((int) $stat['comp_size'] > 0 && (int) $stat['size'] / (int) $stat['comp_size'] > self::MAX_RATIO)) { $zip->close(); throw new RuntimeException('Office package exceeds decompression safety limits.'); }
            if (hrtime(true) - $start > 30_000_000_000) { $zip->close(); throw new RuntimeException('Parser time limit exceeded.'); }
        }
        return $zip;
    }

    private function detectType(string $bytes): ?string
    {
        return match (true) {
            str_starts_with($bytes, '%PDF-') => 'pdf',
            str_starts_with($bytes, "\x89PNG\r\n\x1a\n") => 'png',
            str_starts_with($bytes, "\xff\xd8\xff") => 'jpeg',
            str_starts_with($bytes, "PK\x03\x04") => 'zip-office',
            default => null,
        };
    }

    private function finding(array &$findings, string $rule, string $result, mixed $detail = null): void
    {
        $findings[] = ['rule' => $rule, 'result' => $result, 'detail' => $detail];
    }

    private function saveRun(DocumentFile $file, ?User $actor, array $findings, ?string $source, ?string $code): SubmissionValidationRun
    {
        $last = SubmissionValidationRun::query()->where('document_file_id', $file->id)->max('run_number') ?? 0;
        $hardBlock = collect($findings)->contains(fn ($f) => $f['result'] === 'block');
        return SubmissionValidationRun::query()->create([
            'document_file_id' => $file->id, 'run_number' => $last + 1,
            'status' => $hardBlock ? 'blocked' : 'checked', 'code_source' => $source,
            'extracted_code' => $code, 'findings' => $findings,
            'checked_by_user_id' => $actor?->id, 'checked_at' => now(),
        ]);
    }
}
