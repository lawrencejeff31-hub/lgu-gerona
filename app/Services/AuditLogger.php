<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * Log document creation
     */
    public static function logDocumentCreated(Document $document, ?User $user = null): void
    {
        self::createLog($document, $user, 'document_created', 'Document created', [
            'document_number' => $document->document_number,
            'file_name' => $document->file_name,
            'type' => $document->type,
            'priority' => $document->priority,
            'security_level' => $document->security_level,
        ]);
    }

    /**
     * Log document update
     */
    public static function logDocumentUpdated(Document $document, array $changes, ?User $user = null): void
    {
        self::createLog($document, $user, 'document_updated', 'Document updated', [
            'changes' => $changes,
            'document_number' => $document->document_number,
        ]);
    }

    /**
     * Log document deletion
     */
    public static function logDocumentDeleted(Document $document, ?User $user = null): void
    {
        self::createLog($document, $user, 'document_deleted', 'Document deleted', [
            'document_number' => $document->document_number,
            'file_name' => $document->file_name,
        ]);
    }

    /**
     * Log document status change
     */
    public static function logStatusChange(Document $document, string $oldStatus, string $newStatus, ?string $remarks = null, ?User $user = null): void
    {
        self::createLog($document, $user, 'status_changed', 
            "Status changed from '{$oldStatus}' to '{$newStatus}'" . ($remarks ? ". Remarks: {$remarks}" : ''), [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'remarks' => $remarks,
            'document_number' => $document->document_number,
        ]);
    }

    /**
     * Log document routing
     */
    public static function logDocumentRouted(Document $document, int $fromOfficeId, int $toOfficeId, ?string $remarks = null, ?User $user = null): void
    {
        self::createLog($document, $user, 'document_routed', 'Document routed to another office', [
            'from_office_id' => $fromOfficeId,
            'to_office_id' => $toOfficeId,
            'remarks' => $remarks,
            'document_number' => $document->document_number,
        ]);
    }

    /**
     * Log document sharing
     */
    public static function logDocumentShared(Document $document, string $shareToken, ?User $user = null): void
    {
        self::createLog($document, $user, 'document_shared', 'Document shared via cloud link', [
            'share_token' => $shareToken,
            'document_number' => $document->document_number,
        ]);
    }

    /**
     * Log file access
     */
    public static function logFileAccessed(Document $document, string $filePath, ?User $user = null): void
    {
        self::createLog($document, $user, 'file_accessed', "File accessed: {$filePath}", [
            'file_path' => $filePath,
            'document_number' => $document->document_number,
        ]);
    }

    /**
     * Log QR code scan
     */
    public static function logQRCodeScanned(Document $document, ?User $user = null): void
    {
        self::createLog($document, $user, 'qr_code_scanned', 'QR code scanned', [
            'document_number' => $document->document_number,
            'qr_code_path' => $document->qr_code_path,
        ]);
    }

    /**
     * Log bulk operations
     */
    public static function logBulkOperation(string $operation, array $documentIds, array $metadata = [], ?User $user = null): void
    {
        foreach ($documentIds as $documentId) {
            $document = Document::find($documentId);
            if ($document) {
                self::createLog($document, $user, "bulk_{$operation}", "Bulk operation: {$operation}", array_merge([
                    'operation' => $operation,
                    'document_number' => $document->document_number,
                    'total_documents' => count($documentIds),
                ], $metadata));
            }
        }
    }

    /**
     * Log authentication events
     */
    public static function logAuthEvent(string $event, ?User $user = null, array $metadata = []): void
    {
        $baseMetadata = array_merge([
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'timestamp' => now()->toISOString(),
        ], $metadata);

        // Debug logging
        \Log::info("AuditLogger::logAuthEvent called", [
            'event' => $event,
            'user_id' => $user?->id ?? 'NULL_USER',
            'user_email' => $user?->email ?? 'NULL_EMAIL',
        ]);

        if ($user) {
            // Log authenticated user events
            DocumentLog::create([
                'document_id' => null,
                'user_id' => $user->id,
                'action' => "auth_{$event}",
                'description' => "Authentication event: {$event}",
                'metadata' => array_merge([
                    'event' => $event,
                ], $baseMetadata)
            ]);
            
            \Log::info("AuditLogger::logAuthEvent - Success path executed", [
                'user_id' => $user->id,
                'action' => "auth_{$event}",
            ]);
        } else {
            // Log failed authentication events without user context
            DocumentLog::create([
                'document_id' => null,
                'user_id' => null,
                'action' => "auth_{$event}",
                'description' => "Authentication event: {$event}",
                'metadata' => array_merge([
                    'event' => $event,
                ], $baseMetadata)
            ]);
            
            \Log::info("AuditLogger::logAuthEvent - Failed path executed", [
                'user_id' => null,
                'action' => "auth_{$event}",
            ]);
        }
    }

    /**
     * Log security events
     */
    public static function logSecurityEvent(string $event, ?Document $document = null, ?User $user = null, array $metadata = []): void
    {
        self::createLog($document, $user, "security_{$event}", "Security event: {$event}", array_merge([
            'event' => $event,
            'severity' => 'high',
        ], $metadata));
    }

    /**
     * Log API access
     */
    public static function logApiAccess(string $endpoint, string $method, ?User $user = null, array $metadata = []): void
    {
        \Illuminate\Support\Facades\Log::info('API access logged', [
            'action' => 'api_access',
            'description' => "API access: {$method} {$endpoint}",
            'user_id' => $user?->id ?? Auth::id(),
            'endpoint' => $endpoint,
            'method' => $method,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'timestamp' => now()->toISOString(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Create a standardized log entry
     */
    private static function createLog(?Document $document, ?User $user, string $action, string $description, array $metadata = []): void
    {
        DocumentLog::create([
            'document_id' => $document?->id,
            'user_id' => $user?->id ?? Auth::id(),
            'action' => $action,
            'description' => $description,
            'metadata' => array_merge([
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'timestamp' => now()->toISOString(),
            ], $metadata)
        ]);
    }

    /**
     * Get audit logs with filtering
     */
    public static function getAuditLogs(array $filters = [])
    {
        $query = DocumentLog::with(['document', 'user']);

        if (isset($filters['document_id'])) {
            $query->where('document_id', $filters['document_id']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('description', 'like', "%{$filters['search']}%")
                  ->orWhere('action', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Get security-related logs
     */
    public static function getSecurityLogs(array $filters = [])
    {
        $filters['action'] = 'security_%';
        return self::getAuditLogs($filters)->where('action', 'like', 'security_%');
    }

    /**
     * Get authentication logs
     */
    public static function getAuthLogs(array $filters = [])
    {
        return self::getAuditLogs($filters)->where('action', 'like', 'auth_%');
    }
}