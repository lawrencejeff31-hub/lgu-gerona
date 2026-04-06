<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\QRCode;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Writer;
use SimpleSoftwareIO\QrCode\Facades\QrCode as SimpleQrCode;

class QRCodeController extends Controller
{
    /**
     * Generate QR code for a specific document
     */
    public function generate(Request $request, Document $document)
    {
        try {
            $token = Str::random(32);

            // Generate QR code PNG using BaconQrCode with GD backend
            $renderer = new ImageRenderer(
                new RendererStyle(200, 1),
                new SvgImageBackEnd()
            );
            $writer = new Writer($renderer);
            $qrBinary = $writer->writeString($document->document_number);

            $filename = 'qr_' . $document->id . '_' . time() . '.svg';
            $path = 'qr-codes/' . $filename;

            // Save the QR image
            Storage::disk('public')->put($path, $qrBinary);

            // Save QR record to database
            $qrCode = QRCode::create([
                'document_id' => $document->id,
                'token' => $token,
                'qr_image_path' => $path,
            ]);

            // Log the generation
            DocumentLog::create([
                'document_id' => $document->id,
                'user_id' => $request->user()->id,
                'action' => 'qr_generated',
                'description' => 'QR code generated for document',
            ]);

            return response()->json([
                'token' => $token,
                'qr_url' => route('qr.view', $token),
                'qr_image_url' => Storage::url($path),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate QR codes for a list of numbers
     */
    public function generateGeneric(Request $request)
    {
        $data = $request->validate([
            'numbers' => 'required|array|min:1',
            'numbers.*' => 'required|string|max:255',
        ]);

        $results = [];

        foreach ($data['numbers'] as $number) {
            $token = Str::random(32);
            
            // Generate QR code image using Simple QR Code
            $png = SimpleQrCode::format('png')->size(200)->margin(1)->generate($number);
            
            $filename = 'qr_generic_' . time() . '_' . substr($token, 0, 8) . '.png';
            $path = 'qr-codes/' . $filename;
            Storage::disk('public')->put($path, $png);
            
            $results[] = [
                'value' => $number,
                'token' => $token,
                'qr_image_url' => Storage::url($path),
                'qr_image_path' => $path,
            ];
        }

        return response()->json(['items' => $results]);
    }

    /**
     * Import a file (CSV or TXT) and generate QR codes for each line
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('file');
        $content = file_get_contents($file->getRealPath());
        $lines = preg_split("/\r\n|\r|\n/", trim($content));
        $numbers = array_values(array_filter(array_map('trim', $lines)));

        $request->merge(['numbers' => $numbers]);
        return $this->generateGeneric($request);
    }

    /**
     * View a document via QR code token
     */
    public function view(Request $request, $token)
    {
        $qrCode = QRCode::where('token', $token)->first();

        if (!$qrCode) {
            return response()->json(['error' => 'Invalid QR code'], 404);
        }

        // ✅ Log scan
        $qrCode->increment('scan_count');
        $qrCode->update(['last_scanned_at' => now()]);

        DocumentLog::create([
            'document_id' => $qrCode->document_id,
            'user_id' => $request->user()?->id,
            'action' => 'qr_scanned',
            'description' => 'QR code scanned',
            'metadata' => [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ]);

        $document = $qrCode->document()->with(['documentType', 'department', 'creator'])->first();

        return response()->json([
            'document' => $document,
            'scan_count' => $qrCode->scan_count,
        ]);
    }

    /**
     * Log a scan for a given document
     */
    public function logScan(Request $request, Document $document)
    {
        $qrCode = QRCode::where('document_id', $document->id)
            ->orderByDesc('created_at')
            ->first();

        if (!$qrCode) {
            return response()->json(['error' => 'QR code not found for document'], 404);
        }

        $qrCode->increment('scan_count');
        $qrCode->update(['last_scanned_at' => now()]);

        DocumentLog::create([
            'document_id' => $document->id,
            'user_id' => $request->user()?->id,
            'action' => 'qr_scanned',
            'description' => 'QR code scanned',
            'metadata' => [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ]);

        return response()->json([
            'message' => 'Scan logged',
            'scan_count' => $qrCode->scan_count,
            'last_scanned_at' => $qrCode->last_scanned_at,
        ]);
    }

    /**
     * View a document by its model binding and log scan
     */
    public function viewDocument(Request $request, Document $document)
    {
        $qrCode = QRCode::where('document_id', $document->id)
            ->orderByDesc('created_at')
            ->first();

        if ($qrCode) {
            $qrCode->increment('scan_count');
            $qrCode->update(['last_scanned_at' => now()]);

            DocumentLog::create([
                'document_id' => $document->id,
                'user_id' => $request->user()?->id,
                'action' => 'qr_scanned',
                'description' => 'QR code scanned',
                'metadata' => [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
            ]);
        }

        $document = Document::with(['documentType', 'department', 'creator'])
            ->find($document->id);

        return response()->json([
            'document' => $document,
            'scan_count' => $qrCode?->scan_count ?? 0,
        ]);
    }

    /**
     * Get total QR scans performed by the authenticated user
     */
    public function myScanCount(Request $request)
    {
        $userId = $request->user()->id;

        $count = \App\Models\DocumentLog::where('user_id', $userId)
            ->whereIn('action', ['qr_scanned', 'qr_code_scanned'])
            ->count();

        return response()->json(['count' => $count]);
    }
}