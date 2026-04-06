<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\DocumentLog;
use App\Models\QRCode;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrCodeGenerator;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Writer;

class GsoDocumentController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Document::class, 'document');
    }

    /**
     * Create a new GSO document with automatic QR generation
     */
    public function create(Request $request)
    {
        $request->validate([
            'type' => 'required|in:PR,PO,DV',
            'file_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'priority' => 'required|in:low,medium,high,urgent',
            'security_level' => 'nullable|in:public,internal,confidential,secret',
            'file' => 'nullable|file|mimes:xlsx,xls,pdf,doc,docx|max:10240', // 10MB max
        ]);

        // Get the user's department (should be GSO)
        $user = $request->user();
        
        // Find the appropriate document type
        $documentType = DocumentType::where('code', $request->type)->first();
        if (!$documentType) {
            return response()->json(['error' => 'Invalid document type'], 400);
        }

        // Generate document number
        $documentNumber = $this->generateDocumentNumber($documentType->prefix);

        $filePath = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filePath = $file->store('documents', 'public');
        }

        // Create the document
        $document = Document::create([
            'document_number' => $documentNumber,
            'file_name' => $request->file_name,
            'description' => $request->description,
            'tags' => $request->tags,
            'sender_id' => $user->id,
            'type' => $request->type,
            'security_level' => $request->security_level ?? 'internal',
            'priority' => $request->priority,
            'status' => 'draft',
            'file_path' => $filePath,
            'department_id' => $user->department_id, // Auto-assign to GSO
            'document_type_id' => $documentType->id,
            'current_department_id' => $user->department_id,
            'created_by' => $user->id,
            'title' => $request->file_name, // Use file_name as title
        ]);

        // Auto-generate QR code
        $qrPath = $this->generateQRCode($document);

        // Create initial log
        DocumentLog::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'action' => 'created',
            'description' => 'GSO document created with QR code',
        ]);

        // Load relationships
        $document->load(['documentType', 'department', 'creator', 'sender', 'qrCode']);

        return response()->json([
            'message' => '✅ GSO document created successfully with QR code!',
            'document' => $document,
            'qr_image_url' => Storage::url($qrPath),
            'share_url' => url("/api/documents/share/{$document->document_number}")
        ], 201);
    }

    /**
     * Get recent GSO documents
     */
    public function getRecentDocuments(Request $request)
    {
        $this->authorize('viewAny', Document::class);
        
        $user = $request->user();
        
        $query = Document::where('department_id', $user->department_id)
            ->whereIn('type', ['PR', 'PO', 'DV'])
            ->with(['documentType', 'department', 'creator', 'sender', 'qrCode'])
            ->orderBy('created_at', 'desc')
            ->limit(10);

        // Apply access control for non-admin users
        if (!$user->hasRole('admin')) {
            // Strict: only documents created by the user
            $query->where('created_by', $user->id);
        }

        $documents = $query->get();

        return ApiResponse::success($documents, 'Recent GSO documents retrieved successfully');
    }

    /**
     * Upload documents in bulk via Excel
     */
    public function bulkUpload(Request $request)
    {
        $this->authorize('create', Document::class);
        
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
            'type' => 'required|in:PR,PO,DV',
            'priority' => 'required|in:low,medium,high,urgent',
            'security_level' => 'nullable|in:public,internal,confidential,secret',
        ]);

        try {
            $user = $request->user();
            $documentType = DocumentType::where('code', $request->type)->first();
            
            if (!$documentType) {
                return response()->json(['error' => 'Invalid document type'], 400);
            }

            // Store the uploaded Excel file
            $excelPath = $request->file('file')->store('excel_uploads', 'public');
            
            // In a real implementation, you would parse the Excel file here
            // For now, we'll create a single document representing the Excel upload
            
            $documentNumber = $this->generateDocumentNumber($documentType->prefix);
            
            $document = Document::create([
                'document_number' => $documentNumber,
                'file_name' => $request->file('file')->getClientOriginalName(),
                'description' => "Bulk upload - {$request->type} documents",
                'tags' => ['bulk-upload', $request->type],
                'sender_id' => $user->id,
                'type' => $request->type,
                'security_level' => $request->security_level ?? 'internal',
                'priority' => $request->priority,
                'status' => 'draft',
                'file_path' => $excelPath,
                'department_id' => $user->department_id,
                'document_type_id' => $documentType->id,
                'current_department_id' => $user->department_id,
                'created_by' => $user->id,
                'title' => "Bulk Upload - {$request->type}",
            ]);

            // Generate QR code
            $this->generateQRCode($document);

            // Log the bulk upload
            DocumentLog::create([
                'document_id' => $document->id,
                'user_id' => $user->id,
                'action' => 'bulk_uploaded',
                'description' => 'Documents uploaded via Excel file',
            ]);

            return response()->json([
                'message' => '✅ Excel file uploaded and processed successfully!',
                'document' => $document->load(['documentType', 'department', 'creator', 'sender', 'qrCode']),
                'processed_count' => 1 // In real implementation, this would be the actual count
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to process Excel file',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate cloud sharing link with metadata
     */
    public function generateCloudLink(Request $request, Document $document)
    {
        // Ensure user has access to this document
        if ($document->department_id !== $request->user()->department_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Generate a unique sharing token
        $shareToken = Str::random(64);
        
        // Update document with sharing metadata
        $document->update([
            'metadata' => array_merge($document->metadata ?? [], [
                'cloud_share_token' => $shareToken,
                'cloud_share_generated_at' => now(),
                'cloud_share_generated_by' => $request->user()->id
            ])
        ]);

        // Log the cloud sharing
        DocumentLog::create([
            'document_id' => $document->id,
            'user_id' => $request->user()->id,
            'action' => 'cloud_share_generated',
            'description' => 'Cloud sharing link generated',
        ]);

        $cloudUrl = url("/api/documents/cloud/{$shareToken}");

        return response()->json([
            'message' => 'Cloud sharing link generated successfully',
            'cloud_url' => $cloudUrl,
            'share_url' => url("/api/documents/share/{$document->document_number}"),
            'qr_image_url' => $document->qr_code_path ? Storage::url($document->qr_code_path) : null
        ]);
    }

    /**
     * Access document via cloud sharing token
     */
    public function accessViaCloudToken($token)
    {
        $document = Document::whereJsonContains('metadata->cloud_share_token', $token)
            ->with(['documentType', 'department', 'creator', 'sender', 'qrCode'])
            ->first();

        if (!$document) {
            return response()->json(['error' => 'Invalid or expired sharing link'], 404);
        }

        // Log the cloud access
        DocumentLog::create([
            'document_id' => $document->id,
            'user_id' => null, // Anonymous access
            'action' => 'cloud_accessed',
            'description' => 'Document accessed via cloud sharing link',
            'metadata' => [
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'access_token' => $token
            ]
        ]);

        return response()->json([
            'document' => $document,
            'access_type' => 'cloud_share',
            'accessed_at' => now()
        ]);
    }

    /**
     * Generate QR code for document
     */
    private function generateQRCode(Document $document)
    {
        $token = Str::random(32);

        // Generate QR code PNG encoding document_number directly using BaconQrCode with GD backend
        $renderer = new ImageRenderer(
            new RendererStyle(200, 1),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrBinary = $writer->writeString($document->document_number);
        
        $filename = "qr_{$document->document_number}_" . time() . '.svg';
        $path = "qrcodes/{$filename}";
        
        // Store QR code image
        Storage::disk('public')->put($path, $qrBinary);
        
        // Update document with QR path
        $document->update(['qr_code_path' => $path]);
        
        // Create QR code record
        QRCode::create([
            'document_id' => $document->id,
            'token' => $token,
            'qr_image_path' => $path,
        ]);
        
        return $path;
    }

    /**
     * Generate document number with GSO prefix
     */
    private function generateDocumentNumber($prefix)
    {
        $year = date('Y');
        $lastDocument = Document::where('document_number', 'like', "{$prefix}-{$year}-%")
            ->orderBy('document_number', 'desc')
            ->first();

        if ($lastDocument) {
            $lastNumber = (int) substr($lastDocument->document_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $newNumber);
    }
}