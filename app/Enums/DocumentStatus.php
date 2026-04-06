<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case FOR_APPROVAL = 'for_approval';
    case ROUTED = 'routed';
    case AWAITING_PAYMENT = 'awaiting_payment';
    case PAID = 'paid';
    case RECEIVED = 'received';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case ON_HOLD = 'on_hold';
    case COMPLETED = 'completed';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending',
            self::SUBMITTED => 'Submitted',
            self::UNDER_REVIEW => 'Under Review',
            self::FOR_APPROVAL => 'For Approval',
            self::ROUTED => 'Routed',
            self::AWAITING_PAYMENT => 'Awaiting Payment',
            self::PAID => 'Paid',
            self::RECEIVED => 'Received',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::ON_HOLD => 'On Hold',
            self::COMPLETED => 'Completed',
            self::ARCHIVED => 'Archived',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::PENDING => 'yellow',
            self::SUBMITTED => 'blue',
            self::UNDER_REVIEW => 'purple',
            self::FOR_APPROVAL => 'indigo',
            self::ROUTED => 'teal',
            self::AWAITING_PAYMENT => 'orange',
            self::PAID => 'teal',
            self::RECEIVED => 'indigo',
            self::APPROVED => 'green',
            self::REJECTED => 'red',
            self::ON_HOLD => 'orange',
            self::COMPLETED => 'emerald',
            self::ARCHIVED => 'gray',
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match($this) {
            self::DRAFT => in_array($newStatus, [self::SUBMITTED, self::PENDING]),
            self::PENDING => in_array($newStatus, [self::UNDER_REVIEW, self::SUBMITTED, self::REJECTED, self::ON_HOLD]),
            self::SUBMITTED => in_array($newStatus, [self::RECEIVED, self::REJECTED, self::ON_HOLD]),
            self::UNDER_REVIEW => in_array($newStatus, [self::APPROVED, self::REJECTED, self::ON_HOLD]),
            self::FOR_APPROVAL => in_array($newStatus, [self::APPROVED, self::REJECTED, self::ON_HOLD]),
            self::ROUTED => in_array($newStatus, [self::RECEIVED]),
            self::AWAITING_PAYMENT => in_array($newStatus, [self::PAID, self::ON_HOLD]),
            self::PAID => in_array($newStatus, [self::COMPLETED]),
            self::RECEIVED => in_array($newStatus, [self::APPROVED, self::REJECTED, self::ON_HOLD]),
            self::APPROVED => in_array($newStatus, [self::SUBMITTED, self::COMPLETED]),
            self::REJECTED => in_array($newStatus, [self::SUBMITTED]), // Creator can resubmit after fixing issues
            self::ON_HOLD => in_array($newStatus, [self::SUBMITTED, self::PENDING]),
            self::COMPLETED => false, // Terminal state
            self::ARCHIVED => false, // Terminal state
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}