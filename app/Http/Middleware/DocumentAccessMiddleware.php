<?php

namespace App\Http\Middleware;

use App\Models\Document;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DocumentAccessMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get document ID from route parameters
        $documentId = $request->route('document') ?? $request->route('id');
        
        if (!$documentId) {
            return response()->json(['error' => 'Document not found'], 404);
        }

        $document = Document::find($documentId);
        
        // Ensure we have a single Document instance, not a Collection
        if ($document instanceof \Illuminate\Database\Eloquent\Collection) {
            $document = $document->first();
        }
        
        if (!$document) {
            return response()->json(['error' => 'Document not found'], 404);
        }

        // Check if user has access to this document
        if (!$this->hasDocumentAccess($user, $document)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        // Add document to request for use in controller
        $request->merge(['document_instance' => $document]);

        return $next($request);
    }

    /**
     * Check if user has access to the document
     */
    private function hasDocumentAccess(User $user, Document $document): bool
    {
        // System administrators have access to all documents
        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return true;
        }

        // Document creator always has access
        if ($document->created_by === $user->id || $document->sender_id === $user->id) {
            return true;
        }

        // Explicitly assigned users or receivers have access
        if (($document->assigned_to && (int)$document->assigned_to === (int)$user->id) ||
            ($document->received_by && (int)$document->received_by === (int)$user->id)) {
            return true;
        }

        // Check security level access
        if (!$this->hasSecurityLevelAccess($user, $document->security_level ?? 'public')) {
            return false;
        }

        // Check department access
        if (!$this->hasDepartmentAccess($user, $document)) {
            return false;
        }

        // Check role-based access
        return $this->hasRoleBasedAccess($user, $document);
    }

    /**
     * Check security level access
     */
    private function hasSecurityLevelAccess(User $user, string $securityLevel): bool
    {
        $userClearanceLevel = $user->security_clearance ?? 'public';
        
        $clearanceLevels = [
            'public' => 0,
            'internal' => 1,
            'confidential' => 2,
            'secret' => 3
        ];

        $requiredLevel = $clearanceLevels[$securityLevel] ?? 0;
        $userLevel = $clearanceLevels[$userClearanceLevel] ?? 0;

        return $userLevel >= $requiredLevel;
    }

    /**
     * Check department access
     */
    private function hasDepartmentAccess(User $user, Document $document): bool
    {
        // If document is not assigned to any department, allow access
        if (!$document->department_id && !$document->current_department_id) {
            return true;
        }

        // Check if user belongs to document's department or current department
        if ($user->department_id === $document->department_id || 
            $user->department_id === $document->current_department_id) {
            return true;
        }

        // Check if user has cross-department access permission (using Spatie Permission package)
        if ($user->can('access_all_departments')) {
            return true;
        }

        // Check if document has been routed to user's department
        $hasRouteAccess = $document->routes()
            ->where('to_office_id', $user->department_id)
            ->exists();

        return $hasRouteAccess;
    }

    /**
     * Check role-based access
     */
    private function hasRoleBasedAccess(User $user, Document $document): bool
    {
        // GSO users can access GSO documents
        if ($user->hasRole('gso') && $document->type === 'GSO') {
            return true;
        }

        // Procurement users can access procurement-related documents
        if ($user->hasRole('procurement') && 
            in_array($document->type, ['PR', 'PO', 'bid', 'award', 'contract'])) {
            return true;
        }

        // Finance users can access financial documents
        if ($user->hasRole('finance') && 
            in_array($document->status, ['awaiting_payment', 'paid']) || 
            $document->type === 'DV') {
            return true;
        }

        // Department heads can access documents in their department
        if ($user->hasRole('department_head') && 
            ($user->department_id === $document->department_id || 
             $user->department_id === $document->current_department_id)) {
            return true;
        }

        // Approvers can access documents that need approval
        if ($user->hasRole('approver') && $document->status === 'for_approval') {
            return true;
        }

        return false;
    }
}