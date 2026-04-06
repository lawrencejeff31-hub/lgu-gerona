<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class IssueReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Issue::with(['user', 'resolver']);

        // Apply filters
        if ($request->has('status') && $request->status !== '') {
            $query->byStatus($request->status);
        }

        if ($request->has('type') && $request->type !== '') {
            $query->byType($request->type);
        }

        if ($request->has('priority') && $request->priority !== '') {
            $query->byPriority($request->priority);
        }

        // Search functionality
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('reporter_name', 'like', "%{$search}%")
                  ->orWhere('reporter_email', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $issues = $query->paginate($perPage);

        return response()->json($issues);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|in:bug,feature_request,improvement,question,other',
            'priority' => 'required|in:low,medium,high,urgent',
            'reporter_name' => 'required|string|max:255',
            'reporter_email' => 'required|email|max:255',
            'reporter_phone' => 'nullable|string|max:20',
            'attachments.*' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,txt', // 10MB max per file
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        
        // Handle file uploads
        $attachmentPaths = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('issue-attachments', $filename, 'public');
                $attachmentPaths[] = $path;
            }
        }
        
        $data['attachments'] = $attachmentPaths;
        
        // Set user_id if authenticated
        if (Auth::check()) {
            $data['user_id'] = Auth::id();
        }

        $issue = Issue::create($data);
        $issue->load(['user', 'resolver']);

        return response()->json([
            'message' => 'Issue report submitted successfully',
            'issue' => $issue
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Issue $issue): JsonResponse
    {
        $issue->load(['user', 'resolver']);
        return response()->json($issue);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Issue $issue): JsonResponse
    {
        // Only allow admins to update issues (you may need to implement proper authorization)
        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:open,in_progress,resolved,closed',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'admin_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // If status is being changed to resolved, set resolved_at and resolved_by
        if (isset($data['status']) && $data['status'] === 'resolved' && $issue->status !== 'resolved') {
            $data['resolved_at'] = now();
            $data['resolved_by'] = Auth::id();
        }

        $issue->update($data);
        $issue->load(['user', 'resolver']);

        return response()->json([
            'message' => 'Issue updated successfully',
            'issue' => $issue
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Issue $issue): JsonResponse
    {
        // Delete associated files
        if ($issue->attachments) {
            foreach ($issue->attachments as $attachment) {
                Storage::disk('public')->delete($attachment);
            }
        }

        $issue->delete();

        return response()->json([
            'message' => 'Issue deleted successfully'
        ]);
    }

    /**
     * Get issue statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total' => Issue::count(),
            'open' => Issue::byStatus('open')->count(),
            'in_progress' => Issue::byStatus('in_progress')->count(),
            'resolved' => Issue::byStatus('resolved')->count(),
            'closed' => Issue::byStatus('closed')->count(),
            'by_type' => [
                'bug' => Issue::byType('bug')->count(),
                'feature_request' => Issue::byType('feature_request')->count(),
                'improvement' => Issue::byType('improvement')->count(),
                'question' => Issue::byType('question')->count(),
                'other' => Issue::byType('other')->count(),
            ],
            'by_priority' => [
                'low' => Issue::byPriority('low')->count(),
                'medium' => Issue::byPriority('medium')->count(),
                'high' => Issue::byPriority('high')->count(),
                'urgent' => Issue::byPriority('urgent')->count(),
            ]
        ];

        return response()->json($stats);
    }
}
