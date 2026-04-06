<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any documents.
     */
    public function viewAny(User $user): bool
    {
        // Both admin and user can view documents (filtered by their access level)
        return $user->hasRole(['admin', 'user']);
    }

    /**
     * Determine whether the user can view the document.
     */
    public function view(User $user, Document $document): bool
    {
        // Admins can view any document
        if ($user->hasRole('admin')) {
            return true;
        }

        // Users can view documents they created OR documents assigned to their office
        if ($user->hasRole('user')) {
            return $document->created_by === $user->id 
                || $document->current_department_id === $user->department_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create documents.
     */
    public function create(User $user): bool
    {
        // Both admin and user can create documents
        return $user->hasRole(['admin', 'user']);
    }

    /**
     * Determine whether the user can update the document.
     */
    public function update(User $user, Document $document): bool
    {
        // Admins can edit any document
        if ($user->hasRole('admin')) {
            return true;
        }

        // Users can edit documents they created OR documents in their department
        if ($user->hasRole('user')) {
            return $document->created_by === $user->id 
                || $document->current_department_id === $user->department_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the document.
     */
    public function delete(User $user, Document $document): bool
    {
        // Only admins can delete documents
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can sign the document.
     */
    public function sign(User $user, Document $document): bool
    {
        // Admins can sign any document
        if ($user->hasRole('admin')) {
            return true;
        }

        // Users and dedicated signers can sign if:
        // - They created the document
        // - The document is currently in their department
        // - They are explicitly assigned as the signer (assigned_to)
        if ($user->hasRole('user') || $user->hasRole('signer')) {
            return $document->created_by === $user->id
                || $document->current_department_id === $user->department_id
                || $document->assigned_to === $user->id
                || $document->received_by === $user->id; // allow the actual receiver to sign
        }

        return false;
    }

    /**
     * Determine whether the user can approve the document.
     */
    public function approve(User $user, Document $document): bool
    {
        // Admins can approve any document
        if ($user->hasRole('admin')) {
            return true;
        }

        // Users can approve documents they created OR documents in their department
        if ($user->hasRole('user')) {
            return $document->created_by === $user->id 
                || $document->current_department_id === $user->department_id;
        }

        return false;
    }

    /**
     * Determine whether the user can forward the document.
     */
    public function forward(User $user, Document $document): bool
    {
        // Admins can forward any document
        if ($user->hasRole('admin')) {
            return true;
        }

        // Users can forward documents they created or documents in their office
        if ($user->hasRole('user')) {
            return $document->created_by === $user->id 
                || $document->current_department_id === $user->department_id;
        }

        return false;
    }

    /**
     * Determine whether the user can receive the document.
     */
    public function receive(User $user, Document $document): bool
    {
        // Admins can receive any document
        if ($user->hasRole('admin')) {
            return true;
        }

        // Users can receive documents assigned to their office
        if ($user->hasRole('user')) {
            return $document->current_department_id === $user->department_id;
        }

        return false;
    }

    /**
     * Determine whether the user can put document on hold.
     */
    public function hold(User $user, Document $document): bool
    {
        // Admins can hold any document
        if ($user->hasRole('admin')) {
            return true;
        }

        // Users can hold documents in their office
        if ($user->hasRole('user')) {
            return $document->current_department_id === $user->department_id;
        }

        return false;
    }

    /**
     * Determine whether the user can complete the document.
     */
    public function complete(User $user, Document $document): bool
    {
        // Admins can complete any document
        if ($user->hasRole('admin')) {
            return true;
        }

        // Users can complete documents they created or in their office
        if ($user->hasRole('user')) {
            return $document->created_by === $user->id 
                || $document->current_department_id === $user->department_id;
        }

        return false;
    }

    /**
     * Check if user can access the document based on business rules.
     */
    private function canUserAccessDocument(User $user, Document $document): bool
    {
        // Users can access documents they created or assigned to their office
        return $document->created_by === $user->id 
            || $document->current_department_id === $user->department_id;
    }

    /**
     * Check if document is routed to user's office.
     */
    private function isDocumentRoutedToUserOffice(User $user, Document $document): bool
    {
        return $document->routes()
            ->where('to_office_id', $user->department_id)
            ->exists();
    }
}