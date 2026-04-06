<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Http\Requests\BulkDocumentRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Department;
use App\Models\DocumentLog;
use App\Models\QRCode;
use App\Models\User;
use App\Models\DocumentRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrCodeGenerator;
use App\Services\CacheService;
use App\Services\DocumentWorkflowService;
use App\Services\PNPKISignatureService;
use App\Enums\DocumentStatus;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Writer;

class DocumentController extends Controller
{
    public function __construct(private DocumentWorkflowService $workflowService)
    {
        // Inject workflow service for consistent usage across methods
        $this->authorizeResource(Document::class, 'document');
    }
 
    public function index(Request $request)
    {
        $this->authorize('viewAny', Document::class);
        
        try {
            // Generate cache key based on request parameters
            $cacheKey = CacheService::generateDocumentListKey($request->all());
            
            // Try to get cached results first
            $cachedDocuments = CacheService::getCachedDocumentList($cacheKey);
            if ($cachedDocuments) {
                return ApiResponse::paginated($cachedDocuments, 'Documents retrieved successfully (cached)');
            }
            
            $query = Document::query()->with([
                'documentType',
                'department',
                'currentDepartment',
                'creator',
                'sender',
                'receivedBy',
            ]);

            $user = $request->user();
            if (!$user->hasRole('admin')) {
                $query->where('created_by', $user->id);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('document_number')) {
                $query->where('document_number', $request->document_number);
            }
            
            if ($request->filled('status_in')) {
                $statuses = explode(',', $request->status_in);
                $query->whereIn('status', $statuses);
            }
            if ($request->filled('created_by')) {
                $query->where('created_by', $request->created_by);
            }
            
            if ($request->filled('exclude_assigned_to')) {
                $query->where('assigned_to', '!=', $request->exclude_assigned_to)
                      ->orWhereNull('assigned_to');
            }
            if ($request->filled('current_department_id')) {
                $query->where('current_department_id', $request->current_department_id);
            }
            if ($request->filled('document_type_id')) {
                $query->where('document_type_id', $request->document_type_id);
            }
            if ($request->filled('department_id')) {
                $query->where('department_id', $request->department_id);
            }
            if ($request->filled('priority')) {
                $query->where('priority', $request->priority);
            }

            // Quick filters from dashboard links
            // Mine: restrict to current user's created documents (useful for admin viewing "my" docs)
            if ($request->boolean('mine')) {
                if ($user->hasRole('admin')) {
                    $query->where('created_by', $user->id);
                }
                // Non-admin users are already restricted to their own documents above
            }

            // Assigned to me: show documents assigned to the current user
            if ($request->boolean('assigned_to_me')) {
                $query->where('assigned_to', $user->id);
            }
            // Or explicit assigned_to filter
            if ($request->filled('assigned_to')) {
                $query->where('assigned_to', $request->assigned_to);
            }

            // Overdue: deadline passed and not completed
            if ($request->boolean('overdue')) {
                $query->whereNotNull('deadline')
                      ->whereDate('deadline', '<', now())
                      ->where('status', '!=', 'completed');
            }
    
            // Hold documents filter
            if ($request->filled('hold_documents') && $request->hold_documents) {
                $query->whereHas('logs', function ($logQuery) {
                    $logQuery->where('description', 'like', '%ON HOLD%')
                             ->orWhere('metadata->notes', 'like', '%ON HOLD%');
                });
                
                // Filter by hold category if specified
                if ($request->filled('hold_category')) {
                    $category = $request->hold_category;
                    $query->whereHas('logs', function ($logQuery) use ($category) {
                        $logQuery->where(function ($q) use ($category) {
                            $q->where('description', 'like', "%ON HOLD%{$category}%")
                              ->orWhere('metadata->notes', 'like', "%ON HOLD%{$category}%");
                        });
                    });
                }
            }

            // Exclude hold documents filter (for Incoming page)
            if ($request->filled('exclude_hold_documents') && $request->exclude_hold_documents) {
                $query->whereDoesntHave('logs', function ($logQuery) {
                    $logQuery->where('description', 'like', '%ON HOLD%')
                             ->orWhere('metadata->notes', 'like', '%ON HOLD%');
                });
            }
            
            // Exclude received documents filter (for Incoming page)
            if ($request->filled('exclude_received') && $request->exclude_received) {
                $query->whereNull('received_by')
                      ->whereNull('received_at');
            }
            
            // Exclude documents created by specific user (for Incoming page)
            if ($request->filled('exclude_created_by')) {
                $query->where('created_by', '!=', $request->exclude_created_by);
            }

            // Outgoing status filter (for Outgoing page)
            if ($request->filled('outgoing_status')) {
                $outgoingStatus = $request->outgoing_status;
                $query->whereJsonContains('metadata->outgoing_status', $outgoingStatus);
            }
    
            // Search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('document_number', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('file_name', 'like', "%{$search}%");
                });
            }
    
            // Date filters
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }
    
            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);
    
            // Pagination
            $perPage = min($request->get('per_page', 15), 100);
            $documents = $query->paginate($perPage);
            
            // Cache the results
            CacheService::cacheDocumentList($cacheKey, $documents, CacheService::CACHE_DURATION_SHORT);
    
            return ApiResponse::paginated($documents, 'Documents retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching documents: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::serverError('Failed to fetch documents');
        }
    }
    
        
    

    /**
     * Get documents for Outgoing page (created by current user)
     */
    public function getOutgoing(Request $request)
    {
        $user = $request->user();
        
        $query = Document::query()
            ->with(['documentType', 'currentDepartment', 'routes.toOffice'])
            ->where('created_by', $user->id)
            ->whereIn('status', ['draft', 'submitted', 'received', 'approved', 'completed']);
            
        // Apply filters
        if ($request->filled('status_in')) {
            $statuses = explode(',', $request->status_in);
            $query->whereIn('status', $statuses);
        } elseif ($request->filled('status')) {
            $status = $request->status;
            if (str_contains($status, ',')) {
                $query->whereIn('status', explode(',', $status));
            } else {
                $query->where('status', $status);
            }
        }
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('document_number', 'like', "%{$search}%");
            });
        }
        
        $documents = $query->orderBy('created_at', 'desc')->paginate(15);
        
        return ApiResponse::paginated($documents, 'Outgoing documents retrieved successfully');
    }
    
    /**
     * Get documents for Incoming page (sent to current user's department)
     */
    public function getIncoming(Request $request)
    {
        $user = $request->user();
        // Guard against unauthenticated access to avoid null user errors
        if (!$user) {
            return \App\Http\Responses\ApiResponse::unauthorized('Authentication required');
        }
        
        $query = Document::query()
            ->with(['documentType', 'creator', 'department'])
            ->where('current_department_id', $user->department_id)
            ->where('status', 'submitted')
            ->where('created_by', '!=', $user->id); // Exclude own documents
            
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('document_number', 'like', "%{$search}%");
            });
        }
        
        $documents = $query->orderBy('submitted_at', 'desc')->paginate(15);
        
        return ApiResponse::paginated($documents, 'Incoming documents retrieved successfully');
    }
    
    /**
     * Get documents for Received page (received by current user's department)
     */
    public function getReceived(Request $request)
    {
        $user = $request->user();
        
        $query = Document::query()
            ->with(['documentType', 'creator', 'receivedBy', 'signatures'])
            ->where('current_department_id', $user->department_id)
            ->whereIn('status', ['received', 'approved']);
            
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('document_number', 'like', "%{$search}%");
            });
        }
        
        $documents = $query->orderBy('received_at', 'desc')->paginate(15);
        
        return ApiResponse::paginated($documents, 'Received documents retrieved successfully');
    }
    
    /**
     * Get documents for Hold page (on hold documents)
     */
    public function getOnHold(Request $request)
    {
        $user = $request->user();
        
        $query = Document::query()
            ->with(['documentType', 'creator', 'currentDepartment'])
            ->where('status', 'on_hold');
            
        // Admin sees all, users see only their department's
        if (!$user->hasRole('admin')) {
            $query->where('current_department_id', $user->department_id);
        }
            
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('document_number', 'like', "%{$search}%")
                  ->orWhere('hold_reason', 'like', "%{$search}%");
            });
        }
        
        $documents = $query->orderBy('hold_at', 'desc')->paginate(15);
        
        return ApiResponse::paginated($documents, 'Hold documents retrieved successfully');
    }
    
    /**
     * Get documents for Completed page (completed documents)
     */
    public function getCompleted(Request $request)
    {
        $user = $request->user();
        
        $query = Document::query()
            ->with(['documentType', 'creator', 'approvedBy', 'signatures'])
            ->where('status', 'completed');
            
        // Admin sees all, users see only documents they were involved with
        if (!$user->hasRole('admin')) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhere('approved_by', $user->id)
                  ->orWhereHas('routes', function ($routeQuery) use ($user) {
                      $routeQuery->where('user_id', $user->id);
                  });
            });
        }
            
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('document_number', 'like', "%{$search}%");
            });
        }
        
        $documents = $query->orderBy('completed_at', 'desc')->paginate(15);
        
        return ApiResponse::paginated($documents, 'Completed documents retrieved successfully');
    }
    
    /**
     * Bulk forward documents to another office
     */
    public function bulkForward(BulkDocumentRequest $request)
    {
        $request->validate([
            'document_ids' => 'required|array',
            'document_ids.*' => 'exists:documents,id',
            'to_office_id' => 'required|exists:departments,id',
            'remarks' => 'nullable|string|max:1000',
            'assigned_user_id' => 'nullable|exists:users,id',
        ]);

        $documents = Document::whereIn('id', $request->document_ids)->get();
        foreach ($documents as $document) {
            $this->authorize('update', $document);
        }

        $workflowService = $this->workflowService;
        $successCount = 0;
        $errors = [];

        foreach ($documents as $document) {
            try {
                $workflowService->forwardDocument(
                    $document,
                    $request->to_office_id,
                    $request->remarks,
                    $request->assigned_user_id
                );
                $successCount++;
            } catch (\Exception $e) {
                $errors[] = "Document {$document->document_number}: {$e->getMessage()}";
            }
        }

        return ApiResponse::success([
            'forwarded_count' => $successCount,
            'errors' => $errors
        ], "Successfully forwarded {$successCount} documents");
    }

    public function bulkUpdateDepartment(BulkDocumentRequest $request)
    {
        $request->validate([
            'document_ids' => 'required|array',
            'document_ids.*' => 'exists:documents,id',
            'department_id' => 'required|exists:departments,id',
            'notes' => 'nullable|string|max:1000'
        ]);

        // Authorize each document for update
        $documents = Document::whereIn('id', $request->document_ids)->get();
        foreach ($documents as $document) {
            $this->authorize('update', $document);
        }

        $documentIds = $request->document_ids;
        $departmentId = $request->department_id;
        $notes = $request->notes;

        DB::transaction(function () use ($documentIds, $departmentId, $notes, $request) {
            // Update documents
            Document::whereIn('id', $documentIds)->update([
                'current_department_id' => $departmentId,
                'status' => 'submitted',
                'updated_at' => now()
            ]);

            $targetDept = Department::find($departmentId);
            // Create logs for each document
            foreach ($documentIds as $documentId) {
                DocumentLog::create([
                    'document_id' => $documentId,
                    'user_id' => $request->user()->id,
                    'action' => 'forwarded',
                    'description' => "Forwarded to {$targetDept->name}" . ($notes ? " - {$notes}" : ''),
                ]);
            }
        });

        return ApiResponse::success([
            'updated_count' => count($documentIds)
        ], 'Documents forwarded successfully');
    }

    /**
     * Generate QR code before document creation (for printing on physical document)
     */
    public function generateQR(Request $request)
    {
        try {
            $request->validate([
                'document_type_id' => 'required|exists:document_types,id'
            ]);

            $documentType = DocumentType::find($request->document_type_id);
            $documentNumber = $this->generateDocumentNumber($documentType->prefix ?? 'DOC');

            // Ensure directory exists
            Storage::disk('public')->makeDirectory('qrcodes');

            // Generate QR code PNG using BaconQrCode with GD backend (no Imagick required)
            $renderer = new ImageRenderer(
                new RendererStyle(200, 1),
                new SvgImageBackEnd()
            );
            $writer = new Writer($renderer);
            $qrBinary = $writer->writeString($documentNumber);

            $filename = "qr_{$documentNumber}_" . time() . '.svg';
            $path = "qrcodes/{$filename}";
            Storage::disk('public')->put($path, $qrBinary);

            return ApiResponse::success([
                'document_number' => $documentNumber,
                'qr_code_url' => Storage::url($path),
                'qr_code_path' => $path
            ], 'QR code generated successfully. Print and attach to document before upload.');
        } catch (\Throwable $e) {
            return ApiResponse::error(
                $e->getMessage(),
                500,
                [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
        }
    }

    public function store(StoreDocumentRequest $request)
    {
        try {
            // Use provided document number or generate new one
            $documentNumber = $request->document_number;
            if (!$documentNumber) {
                $documentType = DocumentType::find($request->document_type_id);
                $documentNumber = $this->generateDocumentNumber($documentType->prefix ?? 'DOC');
            }

            $filePath = null;
            $fileName = $request->file_name;

            // Handle file upload
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $fileName = $fileName ?: $file->getClientOriginalName();
                $filePath = $file->store('documents', 'public');
            }

            $document = Document::create([
                'document_number' => $documentNumber,
                'file_name' => $fileName,
                'description' => $request->description,
                'tags' => $request->tags,
                'sender_id' => $request->user()->id,
                'type' => $request->type,
                'security_level' => $request->security_level ?? 'internal',
                'priority' => $request->priority,
                'status' => 'draft',
                'file_path' => $filePath,
                'department_id' => $request->department_id,
                'document_type_id' => $request->document_type_id,
                'current_department_id' => $request->department_id,
                'created_by' => $request->user()->id,
                'title' => $request->title ?: $fileName,
                'deadline' => $request->deadline,
            ]);

            // Generate QR code if not already exists
            $qrCodePath = $request->qr_code_path;
            if (!$qrCodePath) {
                $qrCodePath = $this->generateQRCode($document);
            } else {
                $document->update(['qr_code_path' => $qrCodePath]);
            }

            // Create QR code record
            QRCode::create([
                'document_id' => $document->id,
                'token' => Str::random(32),
                'qr_image_path' => $qrCodePath,
            ]);

            DocumentLog::create([
                'document_id' => $document->id,
                'user_id' => $request->user()->id,
                'action' => 'created',
                'description' => 'Document registered with QR code',
            ]);
            
            CacheService::invalidateDocumentLists();

            return ApiResponse::created(
                $document->load(['documentType', 'department', 'creator', 'sender', 'qrCode']),
                '✅ Document registered successfully.'
            );
        } catch (\Exception $e) {
            Log::error('Error creating document: '.$e->getMessage());
            return ApiResponse::serverError('Failed to create document');
        }
    }

    public function show(Document $document)
    {
        // Try to get cached document first
        $cachedDocument = CacheService::getCachedDocument($document->id);
        if ($cachedDocument) {
            return ApiResponse::success($cachedDocument, 'Document retrieved successfully (cached)');
        }
        
        $document->load([
            'documentType',
            'department',
            'currentDepartment',
            'creator',
            'logs.user',
            'attachments.uploader',
            'qrCode',
            'signatures.user',
            'rejectedBy',
        ]);
        
        // Cache the document
        CacheService::cacheDocument($document);

        return ApiResponse::success($document, 'Document retrieved successfully');
    }

    public function findByBarcode(Request $request)
    {
        $request->validate(['barcode' => 'required|string']);
        $doc = Document::where('barcode', $request->barcode)
            ->with(['documentType','department','currentDepartment','creator','logs.user','attachments.uploader','qrCode','signatures.user','rejectedBy'])
            ->first();
        if (!$doc) return ApiResponse::notFound('Document not found');
        return ApiResponse::success($doc, 'Document found successfully');
    }

    public function receive(Request $request, Document $document)
    {
        $this->authorize('update', $document);
        
        $request->validate(['notes' => 'nullable|string']);
        
        $document->update([
            'status' => 'received',
            'received_by' => $request->user()->id,
            'received_at' => now()
        ]);
        
        DocumentLog::create([
            'document_id' => $document->id,
            'user_id' => $request->user()->id,
            'action' => 'received',
            'description' => 'Document received',
            'metadata' => ['notes' => $request->notes]
        ]);
        
        return response()->json($document->fresh());
    }

    public function reject(Request $request, Document $document)
    {
        $this->authorize('update', $document);
        
        $request->validate(['reason' => 'required|string']);
        $document->update(['status' => 'rejected']);
        DocumentLog::create([
            'document_id' => $document->id,
            'user_id' => $request->user()->id,
            'action' => 'rejected',
            'description' => 'Document rejected',
            'metadata' => ['reason' => $request->reason]
        ]);
        return response()->json($document->fresh());
    }

    public function update(UpdateDocumentRequest $request, Document $document)
    {
        $oldData = $document->toArray();
        $document->update($request->only([
            'title', 'description', 'priority', 'status', 'deadline', 'security_level', 'tags', 'metadata'
        ]));

        // Log changes
        if ($document->wasChanged()) {
            DocumentLog::create([
                'document_id' => $document->id,
                'user_id' => $request->user()->id,
                'action' => 'updated',
                'description' => 'Document updated',
                'metadata' => [
                    'changes' => array_diff_assoc($document->fresh()->toArray(), $oldData)
                ]
            ]);
            
            // Invalidate cache since document was updated
            CacheService::invalidateDocument($document->id);
        }

        return ApiResponse::success($document->load(['documentType', 'department', 'creator']), 'Document updated successfully');
    }

    public function destroy(Document $document)
    {
        // Invalidate cache before deletion
        CacheService::invalidateDocument($document->id);
        
        $document->delete();
        return ApiResponse::success(null, 'Document deleted successfully');
    }

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

    public function bulkUpdateStatus(BulkDocumentRequest $request)
    {
        $request->validate([
            'document_ids' => 'required|array',
            'document_ids.*' => 'exists:documents,id',
            'status' => ['required', 'in:' . implode(',', \App\Enums\DocumentStatus::values())],
            'notes' => 'nullable|string|max:1000'
        ]);

        // Authorize each document for update
        $documents = Document::whereIn('id', $request->document_ids)->get();
        foreach ($documents as $document) {
            $this->authorize('update', $document);
        }

        $documentIds = $request->document_ids;
        $newStatus = $request->status;
        $notes = $request->notes;

        $updated = 0;
        $errors = [];

        foreach ($documentIds as $documentId) {
            try {
                $document = Document::findOrFail($documentId);
                // Prefer workflow service for statuses that have side effects/notifications
                switch ($newStatus) {
                    case DocumentStatus::COMPLETED->value:
                        $this->workflowService->completeDocument($document, $notes);
                        break;
                    case DocumentStatus::APPROVED->value:
                        $this->workflowService->approveDocument($document, $notes);
                        break;
                    case DocumentStatus::REJECTED->value:
                        $this->workflowService->rejectDocument($document, $notes ?? '');
                        break;
                    case DocumentStatus::ON_HOLD->value:
                        $this->workflowService->holdDocument($document, $notes ?? 'On hold');
                        break;
                    case DocumentStatus::RECEIVED->value:
                        $this->workflowService->receiveDocument($document, $notes);
                        break;
                    default:
                        // Fallback: direct update + log
                        DB::transaction(function () use ($document, $newStatus, $notes) {
                            $document->update([
                                'status' => $newStatus,
                                'updated_at' => now()
                            ]);

                            DocumentLog::create([
                                'document_id' => $document->id,
                                'user_id' => Auth::id(),
                                'action' => 'status_changed',
                                'description' => "Status changed to {$newStatus}" . ($notes ? " - {$notes}" : ''),
                            ]);
                        });
                        break;
                }
                $updated++;
            } catch (\Throwable $e) {
                $errors[] = "Document {$documentId}: {$e->getMessage()}";
            }
        }

        return response()->json([
            'message' => 'Documents updated successfully',
            'updated_count' => $updated,
            'errors' => $errors,
        ]);
    }

    public function bulkAssign(Request $request)
    {
        $request->validate([
            'document_ids' => 'required|array',
            'document_ids.*' => 'exists:documents,id',
            'assigned_to' => 'required|exists:users,id',
            'notes' => 'nullable|string|max:1000'
        ]);

        // Authorize each document for update
        $documents = Document::whereIn('id', $request->document_ids)->get();
        foreach ($documents as $document) {
            $this->authorize('update', $document);
        }

        $documentIds = $request->document_ids;
        $assignedTo = $request->assigned_to;
        $notes = $request->notes;

        DB::transaction(function () use ($documentIds, $assignedTo, $notes, $request) {
            // Update documents
            Document::whereIn('id', $documentIds)->update([
                'assigned_to' => $assignedTo,
                'updated_at' => now()
            ]);

            // Create logs for each document
            $assignedUser = User::find($assignedTo);
            foreach ($documentIds as $documentId) {
                DocumentLog::create([
                    'document_id' => $documentId,
                    'user_id' => $request->user()->id,
                    'action' => 'assigned',
                    'description' => "Assigned to {$assignedUser->name}" . ($notes ? " - {$notes}" : ''),
                ]);
            }
        });

        return response()->json([
            'message' => 'Documents assigned successfully',
            'assigned_count' => count($documentIds)
        ]);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'document_ids' => 'required|array',
            'document_ids.*' => 'exists:documents,id'
        ]);

        // Authorize each document for deletion
        $documents = Document::whereIn('id', $request->document_ids)->get();
        foreach ($documents as $document) {
            $this->authorize('delete', $document);
        }

        $documentIds = $request->document_ids;
        
        DB::transaction(function () use ($documentIds, $request) {
            // Log deletion for each document
            foreach ($documentIds as $documentId) {
                DocumentLog::create([
                    'document_id' => $documentId,
                    'user_id' => $request->user()->id,
                    'action' => 'deleted',
                    'description' => 'Document deleted',
                ]);
            }

            // Delete documents (logs will be cascade deleted)
            Document::whereIn('id', $documentIds)->delete();
        });

        return ApiResponse::success([
            'deleted_count' => count($documentIds)
        ], 'Documents deleted successfully');
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Document::class);
        
        $request->validate([
            'format' => 'required|in:csv,excel',
            'filters' => 'nullable|array'
        ]);

        // Apply same filters as index method with eager loading to prevent N+1 queries
        $query = Document::with(['documentType', 'department', 'currentDepartment', 'creator']);
        
        // Apply access control based on user role (same as index method)
        $user = $request->user();
        if (!$user->hasRole('admin')) {
            // Strict: regular users export only their created documents
            $query->where('created_by', $user->id);
        }
        
        $filters = $request->get('filters', []);
        
        // Apply filters (same logic as index method)
        foreach ($filters as $key => $value) {
            if ($value !== '' && $value !== null) {
                switch ($key) {
                    case 'status':
                        $query->where('status', $value);
                        break;
                    case 'type':
                        $query->where('type', $value);
                        break;
                    case 'priority':
                        $query->where('priority', $value);
                        break;
                    case 'search':
                        $query->where(function ($q) use ($value) {
                            $q->where('title', 'like', "%{$value}%")
                              ->orWhere('document_number', 'like', "%{$value}%")
                              ->orWhere('description', 'like', "%{$value}%");
                        });
                        break;
                }
            }
        }

        $documents = $query->get();

        // For now, return JSON data. In production, you'd generate actual CSV/Excel files
        $exportData = $documents->map(function ($doc) {
            return [
                'Document Number' => $doc->document_number,
                'Title' => $doc->title,
                'Type' => $doc->type,
                'Status' => $doc->status,
                'Priority' => $doc->priority,
                'Created By' => $doc->creator->name ?? '',
                'Department' => $doc->department->name ?? '',
                'Current Department' => $doc->currentDepartment->name ?? '',
                'Created At' => $doc->created_at->format('Y-m-d H:i:s'),
                'Deadline' => $doc->deadline ? $doc->deadline->format('Y-m-d') : '',
            ];
        });

        return ApiResponse::success([
            'data' => $exportData,
            'count' => $exportData->count(),
            'format' => $request->format
        ], 'Export data generated successfully');
    }

    public function getStats(Request $request)
    {
        // Try to get cached stats first
        $cachedStats = CacheService::getCachedDocumentStats();
        if ($cachedStats) {
            return ApiResponse::success($cachedStats, 'Document statistics retrieved successfully (cached)');
        }
        
        $stats = [
            'total' => Document::count(),
            'by_status' => Document::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status'),
            'by_priority' => Document::select('priority', DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->pluck('count', 'priority'),
            'by_type' => Document::select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->pluck('count', 'type'),
            'overdue' => Document::where('deadline', '<', now())
                ->whereNotIn('status', ['completed', 'rejected'])
                ->count(),
            'recent' => Document::where('created_at', '>=', now()->subDays(7))->count(),
        ];
        
        // Cache the stats
        CacheService::cacheDocumentStats($stats);

        return ApiResponse::success($stats, 'Document statistics retrieved successfully');
    }

    /**
     * Get time-series stats for documents created within a date range.
     * Returns daily counts and derived weekly aggregates.
     */
    public function getTimeSeries(Request $request)
    {
        try {
            $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
            $dateTo = $request->get('date_to', now()->toDateString());

            // Cache key based on range
            $cacheKey = sprintf('timeseries:%s:%s', $dateFrom, $dateTo);
            $cached = Cache::get(CacheService::PREFIX_DOCUMENT_STATS . $cacheKey);
            if ($cached) {
                Log::info('Document timeseries cache hit', ['key' => $cacheKey]);
                return ApiResponse::success($cached, 'Document time-series retrieved successfully (cached)');
            }

            // Raw daily counts from DB
            $rawDaily = Document::whereBetween('created_at', [$dateFrom, $dateTo])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->pluck('count', 'date')
                ->toArray();

            // Fill missing days with zero for clean line chart
            $period = CarbonPeriod::create($dateFrom, $dateTo);
            $daily = [];
            foreach ($period as $date) {
                $key = $date->toDateString();
                $daily[] = [
                    'date' => $key,
                    'count' => (int)($rawDaily[$key] ?? 0)
                ];
            }

            // Derive weekly aggregates from daily (ISO weeks, Monday start)
            $weeklyMap = [];
            foreach ($daily as $item) {
                $d = Carbon::parse($item['date']);
                $weekStart = $d->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
                if (!isset($weeklyMap[$weekStart])) {
                    $weeklyMap[$weekStart] = 0;
                }
                $weeklyMap[$weekStart] += $item['count'];
            }
            ksort($weeklyMap);
            $weekly = [];
            foreach ($weeklyMap as $weekStart => $count) {
                $weekly[] = [
                    'week_start' => $weekStart,
                    'count' => (int)$count
                ];
            }

            $result = [
                'daily' => $daily,
                'weekly' => $weekly,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ];

            // Cache medium duration
            Cache::put(CacheService::PREFIX_DOCUMENT_STATS . $cacheKey, $result, CacheService::CACHE_DURATION_MEDIUM);
            Log::info('Document timeseries cached', ['key' => $cacheKey]);

            return ApiResponse::success($result, 'Document time-series retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error computing document time-series: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::serverError('Failed to compute document time-series');
        }
    }

    public function getDepartments()
    {
        $departments = \App\Models\Department::all(['id', 'name']);
        return ApiResponse::success($departments, 'Departments retrieved successfully');
    }

    /**
     * Track document by document number or QR code for QR Tracker
     */
    public function trackDocument(Request $request, $identifier)
    {
        try {
            // Try to find document by document number first
            $document = Document::where('document_number', $identifier)->first();
            
            // If not found, try to extract document number from QR URL
            if (!$document && (str_contains($identifier, 'http') || str_contains($identifier, 'documents/code/'))) {
                // Extract document number from QR URL
                if (preg_match('/documents\/code\/([^\/\?]+)/', $identifier, $matches)) {
                    $document = Document::where('document_number', $matches[1])->first();
                }
            }
            
            if (!$document) {
                return ApiResponse::notFound('Document not found');
            }
            
            // Simplified access control for QR tracking
            $user = $request->user();
            
            if ($user) {
                // Admin users can track any document
                if ($user->hasRole('admin')) {
                    // Allow admin access
                } else {
                    // Regular users can track documents they have legitimate access to
                    $canAccess = $document->created_by === $user->id || 
                                $document->current_department_id === $user->department_id ||
                                $document->department_id === $user->department_id;
                                
                    if (!$canAccess) {
                        return ApiResponse::forbidden('Access denied to this document');
                    }
                }
            }
            // Note: QR tracking might be public in some implementations
            // Remove user requirement if public QR tracking is desired
            
            // Load document with all related data
            $document->load([
                'creator',
                'department',
                'currentDepartment',
                'documentType',
                'qrCode',
                'routes' => function($query) {
                    $query->with(['user', 'fromOffice', 'toOffice'])->orderBy('created_at', 'desc');
                },
                'logs' => function($query) {
                    $query->with(['user'])->orderBy('created_at', 'desc');
                },
                'signatures'
            ]);
            
            // Log the tracking access
            DocumentLog::create([
                'document_id' => $document->id,
                'user_id' => $user->id,
                'action' => 'tracked',
                'description' => 'Document tracked via QR Tracker',
                'metadata' => [
                    'identifier' => $identifier,
                    'access_method' => str_contains($identifier, 'http') ? 'qr_code' : 'document_number'
                ]
            ]);

            return ApiResponse::success($document, 'Document tracking information retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error tracking document: ' . $e->getMessage(), [
                'identifier' => $identifier,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return ApiResponse::serverError('Failed to track document');
        }
    }
    private function generateQRCode(Document $document)
    {
        $token = Str::random(32);
        $payload = $document->document_number; // Encode document number directly

        // Generate QR code PNG using BaconQrCode with GD backend
        $renderer = new ImageRenderer(
            new RendererStyle(200, 1),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrBinary = $writer->writeString($payload);
        
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

    public function getHistory(Document $document)
    {
        // Eager load relationships to prevent N+1 queries
        $history = $document->routes()
            ->with(['fromOffice', 'toOffice', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        $logs = $document->logs()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return ApiResponse::success([
            'routes' => $history,
            'logs' => $logs
        ], 'Document history retrieved successfully');
    }

    /**
     * Route document to another office
     */
    public function routeDocument(Request $request, Document $document)
    {
        $this->authorize('update', $document);
        
        $request->validate([
            'to_office_id' => 'required|exists:departments,id',
            'remarks' => 'nullable|string|max:1000'
        ]);

        $route = DocumentRoute::create([
            'document_id' => $document->id,
            'from_office_id' => $document->current_department_id,
            'to_office_id' => $request->to_office_id,
            'user_id' => $request->user()->id,
            'status' => 'sent',
            'remarks' => $request->remarks,
        ]);

        // Update document's current department
        $document->update([
            'current_department_id' => $request->to_office_id,
            'status' => 'submitted'
        ]);

        // Log the routing
        DocumentLog::create([
            'document_id' => $document->id,
            'user_id' => $request->user()->id,
            'action' => 'routed',
            'description' => "Document forwarded to " . Department::find($request->to_office_id)->name,
            'metadata' => ['remarks' => $request->remarks]
        ]);

        return ApiResponse::success(
            $route->load(['fromOffice', 'toOffice', 'user']),
            'Document routed successfully'
        );
    }

    /**
     * Digitally sign a document (PNPKI placeholder)
     */
    public function sign(Request $request, Document $document)
    {
        $this->authorize('update', $document);

        $request->validate([
            'remarks' => 'nullable|string|max:1000',
            // In a real PNPKI flow, we would validate and use a certificate
            // 'certificate' => 'nullable|file',
        ]);

        // Create a pseudo signature file capturing immutable hash of current file
        $contentHash = null;
        if ($document->file_path) {
            $fileContents = Storage::disk('public')->get($document->file_path);
            $contentHash = Hash::make($fileContents);
        }

        $sigPayload = json_encode([
            'document_number' => $document->document_number,
            'signed_by' => $request->user()->id,
            'signed_at' => now()->toIso8601String(),
            'content_hash' => $contentHash,
            'remarks' => $request->remarks,
        ]);

        $filename = 'sig_' . $document->document_number . '_' . time() . '.sig';
        $sigPath = 'signatures/' . $filename;
        Storage::disk('public')->put($sigPath, $sigPayload);

        // Persist signature record
        $signature = \App\Models\Signature::create([
            'document_id' => $document->id,
            'user_id' => $request->user()->id,
            // Column is non-nullable; use empty string placeholder when no image is provided
            'signature_image_path' => '',
            'signature_file_path' => $sigPath,
            'signature_hash' => $contentHash,
            'signed_at' => now(),
            'signature_type' => 'pnpki',
            'verification_status' => 'pending',
        ]);

        // Update status to approved (signed) for the current office
        $document->update(['status' => 'approved']);

        DocumentLog::create([
            'document_id' => $document->id,
            'user_id' => $request->user()->id,
            'action' => 'signed',
            'description' => 'Signed (PNPKI)',
            'metadata' => [
                'sig_path' => $sigPath,
                'remarks' => $request->remarks,
            ],
        ]);

        // Auto-verify PNPKI signature and log outcome
        /** @var PNPKISignatureService $verifier */
        $verifier = app(PNPKISignatureService::class);
        $verified = $verifier->verifySignature($signature);
        $signature->verification_status = $verified ? 'verified' : 'failed';
        $signature->save();

        DocumentLog::create([
            'document_id' => $document->id,
            'user_id' => $request->user()->id,
            'action' => $verified ? 'signature_verified' : 'signature_verification_failed',
            'description' => $verified ? 'Signature verified' : 'Signature verification failed',
            'metadata' => [
                'signature_id' => $signature->id,
                'sig_path' => $sigPath,
            ],
        ]);

        return ApiResponse::success($document->fresh(), 'Document signed successfully');
    }

    /**
     * Approve and mark as completed (Treasury/final office)
     */
    public function approveComplete(Request $request, Document $document)
    {
        $this->authorize('update', $document);
        $request->validate(['remarks' => 'nullable|string|max:1000']);

        $document->update([
            'status' => 'completed',
            'completed_at' => now(),
            'approved_by' => $request->user()->id,
            'approved_at' => now()
        ]);

        DocumentLog::create([
            'document_id' => $document->id,
            'user_id' => $request->user()->id,
            'action' => 'completed',
            'description' => 'Document approved and completed',
            'remarks' => $request->remarks,
            'metadata' => ['remarks' => $request->remarks],
        ]);

        return ApiResponse::success($document->fresh(), 'Document marked as completed');
    }

    /**
     * Put document on hold
     */
    public function hold(Request $request, Document $document)
    {
        $this->authorize('hold', $document);
        
        $request->validate([
            'reason' => 'required|string|max:1000',
            'remarks' => 'nullable|string|max:1000'
        ]);

        $document->update([
            'status' => 'on_hold',
            'hold_reason' => $request->reason,
            'hold_at' => now()
        ]);

        DocumentLog::create([
            'document_id' => $document->id,
            'user_id' => $request->user()->id,
            'action' => 'on_hold',
            'description' => 'Document placed on hold',
            'remarks' => $request->reason,
            'metadata' => [
                'reason' => $request->reason,
                'remarks' => $request->remarks
            ]
        ]);

        return ApiResponse::success($document->fresh(), 'Document placed on hold');
    }

    /**
     * Resume document from hold
     */
    public function resume(Request $request, Document $document)
    {
        $this->authorize('hold', $document);
        
        $request->validate([
            'remarks' => 'nullable|string|max:1000'
        ]);

        // Restore previous status (default to 'submitted' if no previous status)
        $previousStatus = 'submitted';
        $lastLog = $document->logs()
            ->where('action', '!=', 'on_hold')
            ->orderBy('created_at', 'desc')
            ->first();
        
        if ($lastLog && isset($lastLog->metadata['previous_status'])) {
            $previousStatus = $lastLog->metadata['previous_status'];
        }

        $document->update([
            'status' => $previousStatus,
            'hold_reason' => null,
            'hold_at' => null
        ]);

        DocumentLog::create([
            'document_id' => $document->id,
            'user_id' => $request->user()->id,
            'action' => 'resumed',
            'description' => 'Document resumed from hold',
            'remarks' => $request->remarks,
            'metadata' => [
                'previous_status' => $previousStatus,
                'remarks' => $request->remarks
            ]
        ]);

        return ApiResponse::success($document->fresh(), 'Document resumed successfully');
    }

    /**
     * QR Code scanning endpoint
     */
    public function scanQRCode(Request $request)
    {
        $request->validate([
            'qr_data' => 'required|string'
        ]);
        
        // Extract document number from QR data
        $documentNumber = $request->qr_data;
        
        // If QR data is a URL, extract document number
        if (str_contains($documentNumber, 'http')) {
            if (preg_match('/documents\/code\/([^\/\?]+)/', $documentNumber, $matches)) {
                $documentNumber = $matches[1];
            }
        }
        
        return $this->trackDocument($request, $documentNumber);
    }
    
    /**
     * Share document via public link
     */
    public function shareDocument($documentNumber)
    {
        $document = Document::where('document_number', $documentNumber)
            ->with(['documentType', 'department', 'creator', 'sender', 'qrCode'])
            ->first();

        if (!$document) {
            return ApiResponse::notFound('Document not found');
        }

        // Log the share access
        DocumentLog::create([
            'document_id' => $document->id,
            'user_id' => null, // Public access
            'action' => 'shared_access',
            'description' => 'Document accessed via share link',
            'metadata' => [
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]
        ]);

        return ApiResponse::success([
            'document' => $document,
            'share_url' => url("/api/documents/share/{$document->document_number}")
        ], 'Document shared successfully');
    }
}