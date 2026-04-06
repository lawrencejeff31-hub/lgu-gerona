<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Document;
use App\Services\DocumentWorkflowService;
use App\Models\Signature;
use App\Models\DocumentLog;
use App\Services\PDFSignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WorkflowController extends Controller
{
    public function __construct(
        private DocumentWorkflowService $workflowService
    ) {}

    /**
     * Forward document to another office
     */
    public function forward(Request $request, Document $document)
    {
        $this->authorize('update', $document);
        
        $request->validate([
            'to_office_id' => 'required|exists:departments,id',
            'remarks' => 'nullable|string|max:1000',
            'assigned_user_id' => 'nullable|exists:users,id',
        ]);

        try {
            $this->workflowService->forwardDocument(
                $document,
                $request->to_office_id,
                $request->remarks,
                $request->assigned_user_id
            );

            return ApiResponse::success(
                $document->fresh(['currentDepartment', 'routes.toOffice']),
                'Document forwarded successfully'
            );
        } catch (\Exception $e) {
            Log::error('Error forwarding document: ' . $e->getMessage());
            return ApiResponse::serverError('Failed to forward document: ' . $e->getMessage());
        }
    }

    /**
     * Receive document at current office
     */
    public function receive(Request $request, Document $document)
    {
        $this->authorize('update', $document);
        
        $request->validate([
            'notes' => 'nullable|string|max:1000'
        ]);

        try {
            $this->workflowService->receiveDocument($document, $request->notes);

            return ApiResponse::success(
                $document->fresh(['receivedBy']),
                'Document received successfully'
            );
        } catch (\Exception $e) {
            Log::error('Error receiving document: ' . $e->getMessage());
            return ApiResponse::serverError('Failed to receive document: ' . $e->getMessage());
        }
    }

    /**
     * Reject document
     */
    public function reject(Request $request, Document $document)
    {
        $this->authorize('update', $document);
        
        $request->validate([
            'reason' => 'required|string|max:1000'
        ]);

        try {
            $this->workflowService->rejectDocument($document, $request->reason);

            return ApiResponse::success(
                $document->fresh(['rejectedBy']),
                'Document rejected successfully'
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::validationError([
                'status' => [$e->getMessage()],
            ], 'Document cannot be rejected');
        } catch (\Exception $e) {
            Log::error('Error rejecting document: ' . $e->getMessage());
            return ApiResponse::serverError('Failed to reject document: ' . $e->getMessage());
        }
    }

    /**
     * Resubmit document after rejection
     */
    public function resubmit(Request $request, Document $document)
    {
        $this->authorize('update', $document);
        
        $request->validate([
            'remarks' => 'nullable|string|max:1000'
        ]);

        try {
            $this->workflowService->resubmitDocument($document, $request->remarks);

            return ApiResponse::success(
                $document->fresh(),
                'Document resubmitted successfully'
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::validationError([
                'status' => [$e->getMessage()],
            ], 'Document cannot be resubmitted');
        } catch (\Exception $e) {
            Log::error('Error resubmitting document: ' . $e->getMessage());
            return ApiResponse::serverError('Failed to resubmit document: ' . $e->getMessage());
        }
    }

    /**
     * Sign document with PNPKI
     */
    public function sign(Request $request, Document $document)
    {
        // Authorize using the dedicated 'sign' ability
        $this->authorize('sign', $document);
        
        // Ensure the document is in a signable state
        if (!$document->canBeSigned()) {
            throw new \InvalidArgumentException('Document cannot be signed in current status');
        }

        // Require an uploaded signed PDF (this replaces PNPKI generation)
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:20480',
            'remarks' => 'nullable|string|max:1000'
        ]);

        try {
            // Store the uploaded PDF under signatures/external/{document_id}
            $storedPath = $request->file('file')->store("signatures/external/{$document->id}", 'public');
            $contents = Storage::disk('public')->get($storedPath);
            $contentHash = hash('sha256', $contents);

            // Create a signature record for the uploaded PDF
            $signature = Signature::create([
                'document_id' => $document->id,
                'user_id' => $request->user()->id,
                'signature_image_path' => '',
                'signature_file_path' => $storedPath,
                'signature_hash' => $contentHash,
                'signed_at' => now(),
                'signature_type' => 'digital',
                'verification_status' => 'pending',
            ]);

            // Demo mode bypass: either global config or per-request flag
            $demoModeEnabled = $request->boolean('demo_mode') || (bool) config('services.signatures.demo_mode', false);
            if ($demoModeEnabled) {
                // Bypass verification but approve to maintain content functionality
                $signature->verification_status = 'verified';
                $signature->save();

                $document->update([
                    'status' => \App\Enums\DocumentStatus::APPROVED,
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ]);

                // Log bypass explicitly for audit clarity
                DocumentLog::create([
                    'document_id' => $document->id,
                    'user_id' => $request->user()->id,
                    'action' => 'signature_bypassed',
                    'description' => 'Demo mode: digital signature validation disabled',
                    'metadata' => [
                        'signature_id' => $signature->id,
                        'source' => 'pdf_upload',
                        'file_path' => $storedPath,
                        'demo_mode' => true,
                    ],
                ]);

                return ApiResponse::success(
                    $document->fresh(['signatures', 'approvedBy']),
                    'DEMONSTRATION MODE: Digital signature validation disabled. This content is not digitally verified'
                );
            }

            // Verify embedded PDF signature heuristically
            /** @var PDFSignatureService $pdfService */
            $pdfService = app(PDFSignatureService::class);
            $verified = $pdfService->verifySignature($signature);
            $signature->verification_status = $verified ? 'verified' : 'failed';
            $signature->save();

            // Approve ONLY if verified
            if ($verified) {
                $document->update([
                    'status' => \App\Enums\DocumentStatus::APPROVED,
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ]);
            }

            // Log signing outcome
            DocumentLog::create([
                'document_id' => $document->id,
                'user_id' => $request->user()->id,
                'action' => $verified ? 'signature_verified' : 'signature_verification_failed',
                'description' => $verified ? 'Document signed via PDF upload' : 'Document signing failed (PDF upload)',
                'metadata' => [
                    'signature_id' => $signature->id,
                    'source' => 'pdf_upload',
                    'file_path' => $storedPath,
                ],
            ]);

            if (!$verified) {
                // Gather diagnostic details
                $debug = $pdfService->analyzeSignature($signature);
                Log::warning('Digital signature verification failed', [
                    'document_id' => $document->id,
                    'signature_id' => $signature->id,
                    'debug' => $debug,
                ]);
                return ApiResponse::validationError([
                    'signature' => ['Digital signature verification failed'],
                    'debug' => $debug,
                ], 'Document signing failed');
            }

            return ApiResponse::success(
                $document->fresh(['signatures', 'approvedBy']),
                'Document signed and verified successfully'
            );
        } catch (\InvalidArgumentException $e) {
            // Invalid state for signing; return 422 to avoid generic 500
            return ApiResponse::validationError([
                'status' => [$e->getMessage()],
            ], 'Document cannot be signed');
        } catch (\Exception $e) {
            Log::error('Error signing document: ' . $e->getMessage());
            return ApiResponse::serverError('Failed to sign document: ' . $e->getMessage());
        }
    }

    /**
     * Approve document without digital signature
     */
    public function approve(Request $request, Document $document)
    {
        $this->authorize('approve', $document);
        
        $request->validate([
            'remarks' => 'nullable|string|max:1000'
        ]);

        try {
            $this->workflowService->approveDocument($document, $request->remarks);

            return ApiResponse::success(
                $document->fresh(['approvedBy']),
                'Document approved successfully'
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::validationError([
                'status' => [$e->getMessage()],
            ], 'Document cannot be approved');
        } catch (\Exception $e) {
            Log::error('Error approving document: ' . $e->getMessage());
            return ApiResponse::serverError('Failed to approve document: ' . $e->getMessage());
        }
    }

    /**
     * Put document on hold
     */
    public function hold(Request $request, Document $document)
    {
        $this->authorize('update', $document);
        
        $request->validate([
            'reason' => 'required|string|max:1000',
            'remarks' => 'nullable|string|max:1000'
        ]);

        try {
            $this->workflowService->holdDocument($document, $request->reason, $request->remarks);

            return ApiResponse::success(
                $document->fresh(),
                'Document placed on hold successfully'
            );
        } catch (\Exception $e) {
            Log::error('Error holding document: ' . $e->getMessage());
            return ApiResponse::serverError('Failed to hold document: ' . $e->getMessage());
        }
    }

    /**
     * Resume document from hold
     */
    public function resume(Request $request, Document $document)
    {
        $this->authorize('update', $document);
        
        $request->validate([
            'remarks' => 'nullable|string|max:1000'
        ]);

        try {
            $this->workflowService->resumeDocument($document, $request->remarks);

            return ApiResponse::success(
                $document->fresh(),
                'Document resumed successfully'
            );
        } catch (\Exception $e) {
            Log::error('Error resuming document: ' . $e->getMessage());
            return ApiResponse::serverError('Failed to resume document: ' . $e->getMessage());
        }
    }

    /**
     * Complete document (final office)
     */
    public function complete(Request $request, Document $document)
    {
        $this->authorize('update', $document);
        
        $request->validate([
            'remarks' => 'nullable|string|max:1000'
        ]);

        try {
            $this->workflowService->completeDocument($document, $request->remarks);

            return ApiResponse::success(
                $document->fresh(),
                'Document completed successfully'
            );
        } catch (\Exception $e) {
            Log::error('Error completing document: ' . $e->getMessage());
            return ApiResponse::serverError('Failed to complete document: ' . $e->getMessage());
        }
    }

    /**
     * Get document workflow status and available actions
     */
    public function status(Request $request, Document $document)
    {
        $this->authorize('view', $document);

        $user = $request->user();
        
        return ApiResponse::success([
            'current_status' => $document->status,
            'progress_percentage' => $this->workflowService->getProgressPercentage($document),
            'available_actions' => [
                'can_forward' => $document->canBeForwarded() && $this->workflowService->canPerformAction($document, 'forward', $user),
                'can_receive' => $document->canBeReceived() && $this->workflowService->canPerformAction($document, 'receive', $user),
                'can_sign' => $document->canBeSigned() && $this->workflowService->canPerformAction($document, 'sign', $user),
                'can_approve' => $document->canBeApproved() && $this->workflowService->canPerformAction($document, 'approve', $user),
                'can_reject' => $document->canBeRejected() && $this->workflowService->canPerformAction($document, 'reject', $user),
                'can_resubmit' => $document->canBeResubmitted() && $document->created_by === $user->id,
                'can_hold' => $document->canBePutOnHold() && $this->workflowService->canPerformAction($document, 'hold', $user),
                'can_resume' => $document->canBeResumed() && $this->workflowService->canPerformAction($document, 'resume', $user),
                'can_complete' => $document->canBeCompleted() && $this->workflowService->canPerformAction($document, 'complete', $user),
            ],
            'workflow_info' => [
                'current_office' => $document->currentDepartment?->name,
                'received_by' => $document->receivedBy?->name,
                'received_at' => $document->received_at,
                'hold_reason' => $document->hold_reason,
                'hold_at' => $document->hold_at,
            ]
        ], 'Document workflow status retrieved successfully');
    }
}