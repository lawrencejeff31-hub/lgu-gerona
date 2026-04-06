<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Document;
use App\Models\Attachment;
use App\Models\DocumentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentController extends Controller
{
    public function store(Request $request, Document $document)
    {
        $request->validate([
            // Allow Excel, PDF, and common image types; 10MB max per file
            'file' => 'required|file|mimes:xls,xlsx,pdf,jpeg,jpg,png,gif|max:10240',
        ]);

        $file = $request->file('file');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('documents', $filename, 'public');

        $attachment = Attachment::create([
            'document_id' => $document->id,
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
        ]);

        // Log attachment upload
        DocumentLog::create([
            'document_id' => $document->id,
            'user_id' => $request->user()->id,
            'action' => 'attachment_uploaded',
            'description' => "File '{$file->getClientOriginalName()}' uploaded",
        ]);

        return response()->json($attachment->load('uploader'), 201);
    }

    public function download(Attachment $attachment)
    {
        if (!Storage::disk('public')->exists($attachment->file_path)) {
            return ApiResponse::notFound('File not found');
        }

        return Storage::disk('public')->download($attachment->file_path, $attachment->original_name);
    }

    public function view(Attachment $attachment)
    {
        if (!Storage::disk('public')->exists($attachment->file_path)) {
            return ApiResponse::notFound('File not found');
        }

        // Use inline response so browsers can preview PDFs/images directly
        return Storage::disk('public')->response($attachment->file_path, $attachment->original_name);
    }

    public function destroy(Attachment $attachment)
    {
        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return ApiResponse::success(null, 'Attachment deleted successfully');
    }
}