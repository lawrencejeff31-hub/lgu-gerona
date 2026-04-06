<?php

namespace App\Http\Middleware;

use App\Models\Document;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class FileAccessMiddleware
{
    /**
     * Handle an incoming request for file access.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        // Get file path from request
        $filePath = $request->route('path') ?? $request->get('file');
        
        if (!$filePath) {
            return response()->json(['error' => 'File path not provided'], 400);
        }

        // Sanitize file path to prevent directory traversal
        $filePath = $this->sanitizeFilePath($filePath);
        
        if (!$filePath) {
            return response()->json(['error' => 'Invalid file path'], 400);
        }

        // Check if file exists
        if (!Storage::exists($filePath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        // Find document associated with this file
        $document = $this->findDocumentByFilePath($filePath);
        
        if (!$document) {
            return response()->json(['error' => 'Document not found'], 404);
        }

        // Check if user has access to this document
        if (!$this->hasFileAccess($user, $document, $filePath)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        // Log file access
        $this->logFileAccess($user, $document, $filePath);

        // Add document and file info to request
        $request->merge([
            'document_instance' => $document,
            'file_path' => $filePath
        ]);

        return $next($request);
    }

    /**
     * Sanitize file path to prevent directory traversal attacks
     */
    private function sanitizeFilePath(string $filePath): ?string
    {
        // Remove any directory traversal attempts
        $filePath = str_replace(['../', '..\\', '../', '..\\'], '', $filePath);
        
        // Ensure file is within allowed directories
        $allowedPaths = [
            'documents/',
            'attachments/',
            'qr-codes/',
            'uploads/'
        ];

        $isAllowed = false;
        foreach ($allowedPaths as $allowedPath) {
            if (str_starts_with($filePath, $allowedPath)) {
                $isAllowed = true;
                break;
            }
        }

        return $isAllowed ? $filePath : null;
    }

    /**
     * Find document by file path
     */
    private function findDocumentByFilePath(string $filePath): ?Document
    {
        // Try to find by main file path
        $document = Document::where('file_path', $filePath)->first();
        
        if ($document) {
            return $document;
        }

        // Try to find by QR code path
        $document = Document::where('qr_code_path', $filePath)->first();
        
        if ($document) {
            return $document;
        }

        // Try to find by attachment
        $attachment = \App\Models\Attachment::where('file_path', $filePath)->first();
        
        if ($attachment) {
            return $attachment->document;
        }

        return null;
    }

    /**
     * Check if user has access to the file
     */
    private function hasFileAccess($user, Document $document, string $filePath): bool
    {
        // System administrators have access to all files
        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return true;
        }

        // Document creator always has access
        if ($document->created_by === $user->id || $document->sender_id === $user->id) {
            return true;
        }

        // Check security level access
        if (!$this->hasSecurityLevelAccess($user, $document->security_level)) {
            return false;
        }

        // Check department access
        if (!$this->hasDepartmentAccess($user, $document)) {
            return false;
        }

        // Check if it's a QR code (public access for QR codes)
        if (str_contains($filePath, 'qr-codes/') && $document->security_level === 'public') {
            return true;
        }

        // Check role-based access
        return $this->hasRoleBasedAccess($user, $document);
    }

    /**
     * Check security level access
     */
    private function hasSecurityLevelAccess($user, string $securityLevel): bool
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
    private function hasDepartmentAccess($user, Document $document): bool
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

        // Check if user has cross-department access permission
        if ($user->hasPermission('access_all_departments')) {
            return true;
        }

        return false;
    }

    /**
     * Check role-based access
     */
    private function hasRoleBasedAccess($user, Document $document): bool
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
            (in_array($document->status, ['awaiting_payment', 'paid']) || 
             $document->type === 'DV')) {
            return true;
        }

        return false;
    }

    /**
     * Log file access for audit purposes
     */
    private function logFileAccess($user, Document $document, string $filePath): void
    {
        \App\Models\DocumentLog::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'action' => 'file_accessed',
            'description' => "File accessed: {$filePath}",
            'metadata' => [
                'file_path' => $filePath,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->toISOString()
            ]
        ]);
    }
}