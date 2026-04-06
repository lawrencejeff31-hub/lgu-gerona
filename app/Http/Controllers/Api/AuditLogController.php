<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Get all audit logs with filtering
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', \App\Models\DocumentLog::class);

        $filters = $request->only([
            'document_id',
            'user_id', 
            'action',
            'date_from',
            'date_to',
            'search'
        ]);

        $logs = AuditLogger::getAuditLogs($filters)->paginate(20);

        return ApiResponse::paginated($logs, 'Audit logs retrieved successfully');
    }

    /**
     * Get security-related audit logs
     */
    public function security(Request $request)
    {
        $this->authorize('viewAny', \App\Models\DocumentLog::class);

        $filters = $request->only([
            'user_id',
            'date_from', 
            'date_to',
            'search'
        ]);

        $logs = AuditLogger::getSecurityLogs($filters)->paginate(20);

        return ApiResponse::paginated($logs, 'Security audit logs retrieved successfully');
    }

    /**
     * Get authentication-related audit logs
     */
    public function authentication(Request $request)
    {
        $this->authorize('viewAny', \App\Models\DocumentLog::class);

        $filters = $request->only([
            'user_id',
            'date_from',
            'date_to', 
            'search'
        ]);

        $logs = AuditLogger::getAuthLogs($filters)->paginate(20);

        return ApiResponse::paginated($logs, 'Authentication audit logs retrieved successfully');
    }

    /**
     * Get audit logs for a specific document
     */
    public function document(Request $request, int $documentId)
    {
        $this->authorize('viewAny', \App\Models\DocumentLog::class);

        $filters = array_merge($request->only([
            'user_id',
            'action',
            'date_from',
            'date_to'
        ]), ['document_id' => $documentId]);

        $logs = AuditLogger::getAuditLogs($filters)->paginate(20);

        return ApiResponse::paginated($logs, 'Document audit logs retrieved successfully');
    }

    /**
     * Get audit logs for a specific user
     */
    public function user(Request $request, int $userId)
    {
        $this->authorize('viewAny', \App\Models\DocumentLog::class);

        $filters = array_merge($request->only([
            'document_id',
            'action',
            'date_from',
            'date_to'
        ]), ['user_id' => $userId]);

        $logs = AuditLogger::getAuditLogs($filters)->paginate(20);

        return ApiResponse::paginated($logs, 'User audit logs retrieved successfully');
    }

    /**
     * Get audit log statistics
     */
    public function stats(Request $request)
    {
        $this->authorize('viewAny', \App\Models\DocumentLog::class);

        $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());

        $stats = [
            'total_logs' => \App\Models\DocumentLog::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'document_actions' => \App\Models\DocumentLog::whereBetween('created_at', [$dateFrom, $dateTo])
                ->whereNotNull('document_id')
                ->count(),
            'auth_events' => \App\Models\DocumentLog::whereBetween('created_at', [$dateFrom, $dateTo])
                ->where('action', 'like', 'auth_%')
                ->count(),
            'security_events' => \App\Models\DocumentLog::whereBetween('created_at', [$dateFrom, $dateTo])
                ->where('action', 'like', 'security_%')
                ->count(),
            'top_actions' => \App\Models\DocumentLog::whereBetween('created_at', [$dateFrom, $dateTo])
                ->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'top_users' => \App\Models\DocumentLog::whereBetween('created_at', [$dateFrom, $dateTo])
                ->with('user:id,name')
                ->selectRaw('user_id, COUNT(*) as count')
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'daily_activity' => \App\Models\DocumentLog::whereBetween('created_at', [$dateFrom, $dateTo])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
        ];

        return ApiResponse::success($stats, 'Audit log statistics retrieved successfully');
    }

    /**
     * Export audit logs (for future implementation)
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', \App\Models\DocumentLog::class);

        // This would implement CSV/Excel export functionality
        // For now, return a message indicating future implementation
        return ApiResponse::success(null, 'Export functionality will be implemented in future updates');
    }
}