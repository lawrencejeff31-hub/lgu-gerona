<?php

namespace App\Services;

use App\Models\Signature;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * Basic PDF Signature Service
 *
 * Detects presence of an embedded digital signature in a PDF.
 * This is a heuristic check (not full cryptographic validation).
 */
class PDFSignatureService
{
    /**
     * Verify a PDF signature by detecting common signature markers.
     *
     * @param Signature $signature
     * @return bool
     */
    public function verifySignature(Signature $signature): bool
    {
        try {
            // Prefer the signature's own file path (uploaded signed PDF),
            // and fall back to the document's stored file path if needed.
            $path = $signature->signature_file_path ?? ($signature->document?->file_path ?? null);
            if (empty($path) || !Storage::disk('public')->exists($path)) {
                return false;
            }

            $pdfPath = Storage::disk('public')->path($path);
            $content = @file_get_contents($pdfPath);
            if ($content === false || $content === null) {
                return false;
            }

            // Heuristic markers commonly present in signed PDFs
            $hasSigDict = (str_contains($content, '/Type /Sig') || str_contains($content, '/Sig'));
            $hasByteRange = str_contains($content, '/ByteRange');
            $hasContents = str_contains($content, '/Contents');
            $hasSubFilter = (
                str_contains($content, '/SubFilter /adbe.pkcs7.detached') ||
                str_contains($content, '/SubFilter /adbe.pkcs7.sha1') ||
                str_contains($content, '/SubFilter /ETSI.CAdES.detached') ||
                str_contains($content, '/SubFilter /ETSI.RFC3161')
            );

            if (($hasSigDict && $hasByteRange && $hasContents) || ($hasSubFilter && $hasContents)) {
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            Log::error('PDF signature verification error', [
                'signature_id' => $signature->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Analyze the PDF for diagnostic details to help explain verification failure.
     *
     * @param Signature $signature
     * @return array
     */
    public function analyzeSignature(Signature $signature): array
    {
        $path = $signature->signature_file_path ?? ($signature->document?->file_path ?? null);
        $disk = Storage::disk('public');

        $exists = $path ? $disk->exists($path) : false;
        $pdfPath = $exists ? $disk->path($path) : null;
        $size = ($exists && $pdfPath) ? @filesize($pdfPath) : null;
        $content = ($exists && $pdfPath) ? (@file_get_contents($pdfPath) ?: '') : '';
        $hash = $content ? hash('sha256', $content) : null;

        $hasSigDict = $content ? (str_contains($content, '/Type /Sig') || str_contains($content, '/Sig')) : false;
        $hasByteRange = $content ? str_contains($content, '/ByteRange') : false;
        $hasContents = $content ? str_contains($content, '/Contents') : false;
        $subFilters = [
            'adbe.pkcs7.detached',
            'adbe.pkcs7.sha1',
            'ETSI.CAdES.detached',
            'ETSI.RFC3161',
        ];
        $foundSubFilters = [];
        foreach ($subFilters as $sf) {
            if ($content && str_contains($content, '/SubFilter /' . $sf)) {
                $foundSubFilters[] = $sf;
            }
        }

        // Extract small snippets around markers to aid debugging
        $byteRangeSnippet = null;
        $contentsSnippet = null;
        if ($content) {
            $bytePos = strpos($content, '/ByteRange');
            if ($bytePos !== false) {
                $start = max(0, $bytePos - 100);
                $byteRangeSnippet = substr($content, $start, 200);
            }

            $contPos = strpos($content, '/Contents');
            if ($contPos !== false) {
                $start = max(0, $contPos - 100);
                $contentsSnippet = substr($content, $start, 200);
            }
        }

        // Simple reasons to guide next steps
        $reasons = [];
        if (!$exists) $reasons[] = 'File not found in storage.';
        if ($exists && ($size === false || $size === null)) $reasons[] = 'Unable to read file size.';
        if ($exists && $size === 0) $reasons[] = 'File appears to be empty.';
        if (!$hasSigDict) $reasons[] = 'Missing signature dictionary (/Type /Sig).';
        if (!$hasByteRange) $reasons[] = 'Missing /ByteRange marker.';
        if (!$hasContents) $reasons[] = 'Missing /Contents marker.';
        if (empty($foundSubFilters)) $reasons[] = 'No supported /SubFilter found.';

        return [
            'file_path' => $path,
            'file_exists' => $exists,
            'file_size_bytes' => $size,
            'sha256' => $hash,
            'markers' => [
                'hasSigDict' => $hasSigDict,
                'hasByteRange' => $hasByteRange,
                'hasContents' => $hasContents,
                'hasSubFilter' => !empty($foundSubFilters),
                'subFiltersFound' => $foundSubFilters,
            ],
            'snippets' => [
                'byteRange' => $byteRangeSnippet,
                'contents' => $contentsSnippet,
            ],
            'reasons' => $reasons,
        ];
    }
}