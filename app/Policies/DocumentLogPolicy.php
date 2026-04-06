<?php

namespace App\Policies;

use App\Models\DocumentLog;
use App\Models\User;

class DocumentLogPolicy
{
    /**
     * Determine whether the user can view any audit logs.
     */
    public function viewAny(User $user): bool
    {
        // Only administrators can view system-wide audit logs
        return $user->hasRole(['admin', 'super-admin']);
    }

    /**
     * Determine whether the user can view a specific audit log.
     * Keep strict for now (admins only).
     */
    public function view(User $user, DocumentLog $log): bool
    {
        return $user->hasRole(['admin', 'super-admin']);
    }
}