<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Document;
use App\Models\Signature;
use App\Models\DocumentLog;
use App\Services\PNPKISignatureService;
use App\Services\PDFSignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class SignatureController extends Controller
{
    /**
     * List signatures with optional filters.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Signature::query()
            ->with([
                'user:id,name,department_id',
                'user.department:id,name',
                'document:id,document_number,title',
            ]);

        // Restrict visibility for non-admin users to their own signatures
        if (!$user->hasRole('admin')) {
            $query->where('user_id', $user->id);
        }

        // Filters
        if ($request->filled('verification_status')) {
            $query->where('verification_status', $request->verification_status);
        }

        if ($request->filled('signature_type')) {
            $query->where('signature_type', $request->signature_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('signed_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('signed_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('certificate_serial', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('document', function ($dq) use ($search) {
                      $dq->where('document_number', 'like', "%{$search}%")
                         ->orWhere('title', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = (int)($request->get('per_page', 15));
        $signatures = $query->orderBy('signed_at', 'desc')->paginate($perPage);

        return ApiResponse::paginated($signatures, 'Signatures retrieved successfully');
    }

    /**
     * Verify a signature and update its verification status
     */
    public function verify(Request $request, Signature $signature)
    {
        $user = $request->user();

        // Authorization: only admins or the signature owner can verify
        if (!$user->hasRole('admin') && $signature->user_id !== $user->id) {
            return ApiResponse::forbidden('You do not have permission to verify this signature');
        }

        // Route verification by signature type
        if ($signature->isPNPKI()) {
            /** @var PNPKISignatureService $service */
            $service = app(PNPKISignatureService::class);
            $result = $service->verifySignature($signature);
        } elseif (in_array($signature->signature_type, ['pdf', 'digital'], true)) {
            /** @var PDFSignatureService $pdfService */
            $pdfService = app(PDFSignatureService::class);
            $result = $pdfService->verifySignature($signature);
        } else {
            $result = false;
        }

        $signature->verification_status = $result ? 'verified' : 'failed';
        $signature->save();

        // Log verification outcome for audit
        DocumentLog::create([
            'document_id' => $signature->document_id,
            'user_id' => $user->id,
            'action' => $result ? 'signature_verified' : 'signature_verification_failed',
            'description' => $result ? 'Signature verified' : 'Signature verification failed',
            'metadata' => [
                'signature_id' => $signature->id,
                'signature_type' => $signature->signature_type,
            ],
        ]);

        return ApiResponse::success(
            $signature->fresh(['user:id,name', 'document:id,document_number,title']),
            $result ? 'Signature verified successfully' : 'Signature verification failed'
        );
    }

    /**
     * Attach an uploaded signed PDF to a document and auto-verify.
     */
    public function attachPdf(Request $request, Document $document)
    {
        // Use 'sign' ability rather than generic 'update'
        $this->authorize('sign', $document);

        // Accept an uploaded signed PDF file or fallback to the document's current file
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:20480', // up to ~20MB
        ]);

        if (!$request->hasFile('file')) {
            return ApiResponse::validationError([
                'file' => ['Signed PDF upload is required']
            ], 'Signed PDF missing');
        }

        $storedPath = $request->file('file')->store("signatures/external/{$document->id}", 'public');
        $filePath = $storedPath;

        // Ensure file exists and is a PDF on the public disk
        if (empty($filePath) || !Storage::disk('public')->exists($filePath)) {
            return ApiResponse::validationError([
                'file' => ['No PDF uploaded and document file not accessible']
            ], 'Signed PDF missing');
        }

        // The mimes:pdf validation above ensures it's a PDF

        // Compute content hash to populate required column
        $contents = Storage::disk('public')->get($filePath);
        $contentHash = hash('sha256', $contents);

        // Create a signature record representing the embedded PDF signature
        $signature = Signature::create([
            'document_id' => $document->id,
            'user_id' => $request->user()->id,
            'signature_image_path' => '',
            'signature_file_path' => $filePath, // for PDF, reference uploaded or document file
            'signature_hash' => $contentHash,
            'signed_at' => now(),
            'signature_type' => 'digital', // use existing enum value for externally signed PDFs
            'verification_status' => 'pending',
        ]);

        // Auto-verify via PDF heuristics
        /** @var PDFSignatureService $pdfService */
        $pdfService = app(PDFSignatureService::class);
        $verified = $pdfService->verifySignature($signature);
        $signature->verification_status = $verified ? 'verified' : 'failed';
        $signature->save();

        // Mark document as approved ONLY upon successful verification
        if ($verified) {
            $document->update(['status' => 'approved']);
        }

        // Log the attach + verification attempt
        DocumentLog::create([
            'document_id' => $document->id,
            'user_id' => $request->user()->id,
            'action' => $verified ? 'signature_verified' : 'signature_verification_failed',
            'description' => $verified ? 'External PDF signature verified' : 'External PDF signature verification failed',
            'metadata' => [
                'signature_id' => $signature->id,
                'source' => 'pdf_upload',
                'file_path' => $filePath,
            ],
        ]);

        if (!$verified) {
            return ApiResponse::validationError([
                'signature' => ['Embedded signature not detected or invalid']
            ], 'PDF signature verification failed');
        }

        return ApiResponse::success(
            $signature->fresh(['user:id,name', 'document:id,document_number,title']),
            'PDF signature attached and verified'
        );
    }

    /**
     * Attach an uploaded signed PDF using a document number (e.g., PR-2025-0002).
     */
    public function attachPdfByNumber(Request $request, string $documentNumber)
    {
        $document = Document::where('document_number', $documentNumber)->first();
        if (!$document) {
            return ApiResponse::notFound('Document not found');
        }

        return $this->attachPdf($request, $document);
    }
}