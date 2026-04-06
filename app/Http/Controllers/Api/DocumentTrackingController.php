<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentTrackingRequest;
use App\Http\Responses\ApiResponse;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentTracking;
use App\Services\CacheService;
use Illuminate\Http\Request;

class DocumentTrackingController extends Controller
{
    public function index(Request $request)
    {
        $query = DocumentTracking::with(['document', 'user']);

        // Filter by document
        if ($request->has('document_id')) {
            $query->where('document_id', $request->document_id);
        }

        // Filter by action
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Date range filter
        if ($request->has('date_from')) {
            $query->whereDate('action_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('action_date', '<=', $request->date_to);
        }

        $tracking = $query->orderBy('action_date', 'desc')->paginate(20);

        return ApiResponse::paginated($tracking, 'Document tracking records retrieved successfully');
    }

    public function store(StoreDocumentTrackingRequest $request)
    {
        $tracking = DocumentTracking::create([
            'document_id' => $request->document_id,
            'user_id' => $request->user()->id,
            'action' => $request->action,
            'notes' => $request->notes,
            'changes' => $request->changes,
            'action_date' => now(),
        ]);

        return ApiResponse::created($tracking->load(['document', 'user']), 'Document tracking record created successfully');
    }

    public function show(DocumentTracking $tracking)
    {
        return ApiResponse::success($tracking->load(['document', 'user']), 'Document tracking record retrieved successfully');
    }

    public function getDocumentHistory(Document $document)
    {
        $history = DocumentTracking::where('document_id', $document->id)
            ->with('user')
            ->orderBy('action_date', 'desc')
            ->get();

        return ApiResponse::success($history, 'Document history retrieved successfully');
    }

    public function getDashboardStats(Request $request)
    {
        $user = $request->user();
        
        // Try cached stats first (per user)
        $cached = CacheService::getCachedDashboardStats($user->id);
        if ($cached) {
            return response()->json($cached);
        }
        
        $stats = [
            'total_documents' => Document::count(),
            'my_documents' => Document::where('created_by', $user->id)->count(),
            'assigned_to_me' => Document::where('assigned_to', $user->id)->count(),
            'pending_review' => Document::where('status', DocumentStatus::UNDER_REVIEW->value)->count(),
            'overdue' => Document::where('deadline', '<', now())
                ->whereNotIn('status', [DocumentStatus::COMPLETED->value, DocumentStatus::REJECTED->value])
                ->count(),
            'recent_activity' => DocumentTracking::with(['document:id,title', 'user:id,name'])
                ->select(['id','document_id','user_id','action','notes','changes','action_date'])
                ->orderBy('action_date', 'desc')
                ->limit(10)
                ->get(),
        ];

        // Cache the computed stats briefly
        CacheService::cacheDashboardStats($user->id, $stats);
        return response()->json($stats);
    }
}
