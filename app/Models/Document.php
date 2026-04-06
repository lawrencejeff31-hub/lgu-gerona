<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_number',
        'file_name',
        'description',
        'tags',
        'sender_id',
        'type',
        'security_level',
        'priority',
        'status',
        'qr_code_path',
        'file_path',
        'department_id',
        'document_type_id',
        'current_department_id',
        'created_by',
        'barcode',
        'title',
        'submission_date',
        'deadline',
        'metadata',
        'submitted_at',
        'received_at',
        'approved_at',
        'rejected_at',
        'completed_at',
        'archived_at',
        'approved_by',
        'rejected_by',
        'rejection_reason',
        'assigned_to',
        'received_by',
        'hold_reason',
        'hold_at',
    ];

    protected $casts = [
        'submission_date' => 'date',
        'deadline' => 'date',
        'submitted_at' => 'datetime',
        'received_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'completed_at' => 'datetime',
        'archived_at' => 'datetime',
        'hold_at' => 'datetime',
        'metadata' => 'array',
        'tags' => 'array',
        'status' => DocumentStatus::class,
    ];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function currentDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'current_department_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DocumentLog::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function qrCode(): HasOne
    {
        return $this->hasOne(QRCode::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(Signature::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(DocumentRoute::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    // Workflow helper methods
    public function canTransitionTo(DocumentStatus $newStatus): bool
    {
        return $this->status->canTransitionTo($newStatus);
    }

    public function isInStatus(DocumentStatus $status): bool
    {
        return $this->status === $status;
    }

    public function canBeForwarded(): bool
    {
        return in_array($this->status, [
            DocumentStatus::DRAFT,
            DocumentStatus::SUBMITTED,
            DocumentStatus::RECEIVED,
            DocumentStatus::APPROVED,
        ]);
    }

    public function canBeReceived(): bool
    {
        return $this->status === DocumentStatus::SUBMITTED;
    }

    public function canBeSigned(): bool
    {
        return $this->status === DocumentStatus::RECEIVED;
    }

    public function canBeApproved(): bool
    {
        return in_array($this->status, [
            DocumentStatus::RECEIVED,
            DocumentStatus::UNDER_REVIEW,
            DocumentStatus::FOR_APPROVAL,
        ]);
    }

    public function canBeCompleted(): bool
    {
        return $this->status === DocumentStatus::APPROVED;
    }

    public function canBePutOnHold(): bool
    {
        return in_array($this->status, [DocumentStatus::SUBMITTED, DocumentStatus::RECEIVED]);
    }

    public function canBeResumed(): bool
    {
        return $this->status === DocumentStatus::ON_HOLD;
    }

    /**
     * Determine if the document can be rejected based on current status.
     */
    public function canBeRejected(): bool
    {
        return $this->status->canTransitionTo(DocumentStatus::REJECTED);
    }

    /**
     * Determine if the document can be resubmitted based on current status.
     */
    public function canBeResubmitted(): bool
    {
        return $this->status === DocumentStatus::REJECTED;
    }
}