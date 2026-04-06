<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentLog;
use App\Models\DocumentRoute;
use App\Models\Department;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Support\Facades\Notification;
use App\Models\Signature;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\PNPKISignatureService;
use InvalidArgumentException;

class DocumentWorkflowService
{

    /**
     * Office-based permissions for workflow actions
     */
    private const OFFICE_PERMISSIONS = [
        'forward' => ['admin', 'user'],
        'receive' => ['admin', 'user'],
        'sign' => ['admin', 'user'],
        'approve' => ['admin', 'user'],
        'reject' => ['admin', 'user'],
        'resubmit' => ['admin', 'user'],
        'hold' => ['admin', 'user'],
        'resume' => ['admin', 'user'],
        'complete' => ['admin', 'user'],
    ];

    /**
     * Forward document to another office
     */
    public function forwardDocument(Document $document, int $toOfficeId, ?string $remarks = null, ?int $assignedUserId = null): bool
    {
        if (!$document->canBeForwarded()) {
            throw new InvalidArgumentException('Document cannot be forwarded in current status');
        }

        $user = Auth::user();
        $toOffice = Department::findOrFail($toOfficeId);

        DB::transaction(function () use ($document, $toOfficeId, $toOffice, $remarks, $user, $assignedUserId) {
            // Create route record
            DocumentRoute::create([
                'document_id' => $document->id,
                'from_office_id' => $document->current_department_id,
                'to_office_id' => $toOfficeId,
                'user_id' => $user->id,
                'status' => 'sent',
                'remarks' => $remarks,
            ]);

            // Update document
            $updateData = [
                'current_department_id' => $toOfficeId,
                'status' => DocumentStatus::SUBMITTED,
                'submitted_at' => now(),
            ];
            if (!is_null($assignedUserId)) {
                $updateData['assigned_to'] = $assignedUserId;
            }
            $document->update($updateData);

            // Log the action
            $this->logAction($document, 'forwarded', "Document forwarded to {$toOffice->name}", [
                'to_office' => $toOffice->name,
                'remarks' => $remarks,
                'assigned_user_id' => $assignedUserId,
            ]);

            // Notify: receiving office users, creator, and assigned user if any
            $recipients = [];
            foreach ($toOffice->users as $deptUser) {
                $recipients[] = $deptUser;
            }
            if ($document->creator) {
                $recipients[] = $document->creator;
            }
            if (!is_null($assignedUserId)) {
                $assigned = User::find($assignedUserId);
                if ($assigned) $recipients[] = $assigned;
            }

            $this->notifyUsers(
                $document->fresh(['creator']),
                'forwarded',
                'Document forwarded',
                "{$document->document_number} forwarded to {$toOffice->name}" . ($assignedUserId ? " and assigned" : ''),
                [
                    'to_office_id' => $toOfficeId,
                    'to_office_name' => $toOffice->name,
                    'assigned_user_id' => $assignedUserId,
                ],
                $recipients
            );
        });

        return true;
    }

    /**
     * Receive document at current office
     */
    public function receiveDocument(Document $document, ?string $notes = null): bool
    {
        if (!$document->canBeReceived()) {
            throw new InvalidArgumentException('Document cannot be received in current status');
        }

        $user = Auth::user();
        
        $document->update([
            'status' => DocumentStatus::RECEIVED,
            'received_by' => $user->id,
            'received_at' => now(),
        ]);

        $this->logAction($document, 'received', 'Document received', [
            'notes' => $notes,
        ]);

        // Notify: creator and assigned user
        $recipients = [];
        if ($document->creator) {
            $recipients[] = $document->creator;
        }
        if ($document->assignedTo) {
            $recipients[] = $document->assignedTo;
        }
        $dept = $document->currentDepartment;
        $by = Auth::user()?->name;
        $this->notifyUsers(
            $document->fresh(['creator', 'assignedTo', 'currentDepartment']),
            'received',
            'Document received',
            "{$document->document_number} received at {$dept?->name} by {$by}",
            [
                'current_office_id' => $dept?->id,
                'current_office_name' => $dept?->name,
                'notes' => $notes,
            ],
            $recipients
        );

        return true;
    }

    /**
     * Reject document
     */
    public function rejectDocument(Document $document, string $reason): bool
    {
        if (!$document->canBeRejected()) {
            throw new InvalidArgumentException('Document cannot be rejected in current status');
        }

        $document->update([
            'status' => DocumentStatus::REJECTED,
            'rejected_by' => Auth::id(),
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->logAction($document, 'rejected', 'Document rejected', [
            'reason' => $reason,
        ]);

        // Notify: creator and assigned user
        $recipients = [];
        if ($document->creator) {
            $recipients[] = $document->creator;
        }
        if ($document->assignedTo) {
            $recipients[] = $document->assignedTo;
        }
        $by = Auth::user()?->name;
        $this->notifyUsers(
            $document->fresh(['creator', 'assignedTo']),
            'rejected',
            'Document rejected',
            "{$document->document_number} rejected by {$by}",
            [
                'reason' => $reason,
            ],
            $recipients
        );

        return true;
    }

    /**
     * Resubmit document after rejection
     */
    public function resubmitDocument(Document $document, ?string $remarks = null): bool
    {
        if (!$document->canBeResubmitted()) {
            throw new InvalidArgumentException('Document cannot be resubmitted in current status');
        }

        // Only the creator can resubmit
        if ($document->created_by !== Auth::id()) {
            throw new InvalidArgumentException('Only the document creator can resubmit');
        }

        $document->update([
            'status' => DocumentStatus::SUBMITTED,
            'submitted_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);

        $this->logAction($document, 'resubmitted', 'Document resubmitted after rejection', [
            'remarks' => $remarks,
        ]);

        // Notify relevant users about resubmission
        $recipients = [];
        if ($document->assignedTo) {
            $recipients[] = $document->assignedTo;
        }
        // Also notify department users who might need to review
        $departmentUsers = $document->department?->users ?? [];
        $recipients = array_merge($recipients, $departmentUsers);

        $by = Auth::user()?->name;
        $this->notifyUsers(
            $document->fresh(['creator', 'assignedTo']),
            'resubmitted',
            'Document resubmitted',
            "{$document->document_number} resubmitted by {$by}",
            [
                'remarks' => $remarks,
            ],
            $recipients
        );

        return true;
    }

    /**
     * Sign document with PNPKI
     */
    public function signDocument(Document $document, ?string $remarks = null): bool
    {
        if (!$document->canBeSigned()) {
            throw new InvalidArgumentException('Document cannot be signed in current status');
        }

        $user = Auth::user();
        
        $verified = false;

        DB::transaction(function () use ($document, $remarks, $user, &$verified) {
            // Prepare signature payload and file
            $contentHash = null;
            if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
                $fileContents = Storage::disk('public')->get($document->file_path);
                $contentHash = hash('sha256', $fileContents);
            }

            $payload = json_encode([
                'document_number' => $document->document_number,
                'signed_by' => $user->id,
                'signed_at' => now()->toIso8601String(),
                'content_hash' => $contentHash,
                'remarks' => $remarks,
            ]);

            $filename = 'sig_' . $document->document_number . '_' . time() . '.sig';
            $sigPath = 'signatures/' . $filename;
            Storage::disk('public')->put($sigPath, $payload);

            // Create signature record with PNPKI type
            $signature = Signature::create([
                'document_id' => $document->id,
                'user_id' => $user->id,
                'signature_image_path' => '',
                'signature_file_path' => $sigPath,
                'signature_hash' => $contentHash ?? hash('sha256', $document->document_number . now()->timestamp),
                'signed_at' => now(),
                'certificate_serial' => 'PNPKI-' . strtoupper(substr(md5($user->id . time()), 0, 10)),
                'signature_type' => 'pnpki',
                'verification_status' => 'pending',
            ]);

            $this->logAction($document, 'signed', 'Document signed (PNPKI)', [
                'remarks' => $remarks,
            ]);

            // Auto-verify PNPKI signature
            /** @var PNPKISignatureService $verifier */
            $verifier = app(PNPKISignatureService::class);
            $verified = $verifier->verifySignature($signature);
            $signature->verification_status = $verified ? 'verified' : 'failed';
            $signature->save();

            // Approve document ONLY if verification succeeds
            if ($verified) {
                $document->update([
                    'status' => DocumentStatus::APPROVED,
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                ]);
            }

            $this->logAction(
                $document,
                $verified ? 'signature_verified' : 'signature_verification_failed',
                $verified ? 'Signature verified' : 'Signature verification failed',
                [
                    'signature_id' => $signature->id,
                    'signature_type' => 'pnpki',
                ]
            );
        });

        return $verified;
    }

    /**
     * Approve document without digital signature
     */
    public function approveDocument(Document $document, ?string $remarks = null): bool
    {
        if (!$document->canBeApproved()) {
            throw new InvalidArgumentException('Document cannot be approved in current status');
        }

        $user = Auth::user();

        $document->update([
            'status' => DocumentStatus::APPROVED,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $this->logAction($document, 'approved', 'Document approved', [
            'remarks' => $remarks,
        ]);

        // Notify: creator and assigned user
        $recipients = [];
        if ($document->creator) {
            $recipients[] = $document->creator;
        }
        if ($document->assignedTo) {
            $recipients[] = $document->assignedTo;
        }
        $by = Auth::user()?->name;
        $dept = Auth::user()?->department?->name;
        $this->notifyUsers(
            $document->fresh(['creator', 'assignedTo']),
            'approved',
            'Document approved',
            "{$document->document_number} approved by {$by}" . ($dept ? " ({$dept})" : ''),
            [
                'remarks' => $remarks,
            ],
            $recipients
        );

        return true;
    }

    /**
     * Put document on hold
     */
    public function holdDocument(Document $document, string $reason, ?string $remarks = null): bool
    {
        if (!$document->canBePutOnHold()) {
            throw new InvalidArgumentException('Document cannot be put on hold in current status');
        }

        $document->update([
            'status' => DocumentStatus::ON_HOLD,
            'hold_reason' => $reason,
            'hold_at' => now(),
        ]);

        $this->logAction($document, 'on_hold', 'Document placed on hold', [
            'reason' => $reason,
            'remarks' => $remarks,
        ]);

        $recipients = [];
        if ($document->creator) {
            $recipients[] = $document->creator;
        }
        if ($document->assignedTo) {
            $recipients[] = $document->assignedTo;
        }
        $this->notifyUsers(
            $document->fresh(['creator', 'assignedTo']),
            'on_hold',
            'Document placed on hold',
            "{$document->document_number} placed on hold",
            [
                'reason' => $reason,
                'remarks' => $remarks,
            ],
            $recipients
        );

        return true;
    }

    /**
     * Resume document from hold
     */
    public function resumeDocument(Document $document, ?string $remarks = null): bool
    {
        if (!$document->canBeResumed()) {
            throw new InvalidArgumentException('Document cannot be resumed - not on hold');
        }

        $document->update([
            'status' => DocumentStatus::SUBMITTED,
            'hold_reason' => null,
            'hold_at' => null,
        ]);

        $this->logAction($document, 'resumed', 'Document resumed from hold', [
            'remarks' => $remarks,
        ]);

        $recipients = [];
        if ($document->creator) {
            $recipients[] = $document->creator;
        }
        if ($document->assignedTo) {
            $recipients[] = $document->assignedTo;
        }
        $this->notifyUsers(
            $document->fresh(['creator', 'assignedTo']),
            'resumed',
            'Document resumed',
            "{$document->document_number} resumed from hold",
            [
                'remarks' => $remarks,
            ],
            $recipients
        );

        return true;
    }

    /**
     * Complete document (final office)
     */
    public function completeDocument(Document $document, ?string $remarks = null): bool
    {
        if (!$document->canBeCompleted()) {
            throw new InvalidArgumentException('Document cannot be completed in current status');
        }

        $document->update([
            'status' => DocumentStatus::COMPLETED,
            'completed_at' => now(),
        ]);

        $this->logAction($document, 'completed', 'Document approved and completed', [
            'remarks' => $remarks,
        ]);

        $recipients = [];
        if ($document->creator) {
            $recipients[] = $document->creator;
        }
        if ($document->assignedTo) {
            $recipients[] = $document->assignedTo;
        }
        $this->notifyUsers(
            $document->fresh(['creator', 'assignedTo']),
            'completed',
            'Document completed',
            "{$document->document_number} completed",
            [
                'remarks' => $remarks,
            ],
            $recipients
        );

        return true;
    }

    /**
     * Check if user can perform action on document
     */
    public function canPerformAction(Document $document, string $action, $user = null): bool
    {
        $user = $user ?? Auth::user();
        
        // Admin can perform all actions
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check if user is in the current department
        if ($document->current_department_id !== $user->department_id) {
            return false;
        }

        // Check action-specific permissions
        return in_array($user->getRoleNames()->first(), self::OFFICE_PERMISSIONS[$action] ?? []);
    }

    /**
     * Get workflow progress percentage
     */
    public function getProgressPercentage(Document $document): int
    {
        return match($document->status) {
            DocumentStatus::DRAFT => 10,
            DocumentStatus::SUBMITTED => 30,
            DocumentStatus::RECEIVED => 50,
            DocumentStatus::APPROVED => 80,
            DocumentStatus::COMPLETED => 100,
            DocumentStatus::REJECTED => 0,
            DocumentStatus::ON_HOLD => 40,
        };
    }

    /**
     * Log workflow action
     */
    private function logAction(Document $document, string $action, string $description, array $metadata = []): void
    {
        DocumentLog::create([
            'document_id' => $document->id,
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => $description,
            'metadata' => array_merge($metadata, [
                'timestamp' => now()->toISOString(),
                'status' => $document->status->value,
            ]),
        ]);
    }

    /**
     * Dispatch notifications to relevant users for a workflow event.
     */
    private function notifyUsers(Document $document, string $event, string $title, string $message, array $extra = [], array $recipients = []): void
    {
        // Build context
        $payload = array_merge([
            'document_id' => $document->id,
            'document_number' => $document->document_number,
        ], $extra);

        // Normalize recipients to unique user set
        $unique = [];
        $users = [];
        foreach ($recipients as $user) {
            if (!$user instanceof User) {
                continue;
            }
            if (!isset($unique[$user->id])) {
                $unique[$user->id] = true;
                $users[] = $user;
            }
        }

        if (count($users) === 0) {
            return;
        }

        Notification::send($users, new GenericNotification($title, $message, 'document.' . $event, $payload));
    }
}