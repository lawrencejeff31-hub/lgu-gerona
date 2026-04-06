<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Issue extends Model
{
    protected $fillable = [
        'title',
        'description',
        'type',
        'priority',
        'status',
        'reporter_name',
        'reporter_email',
        'reporter_phone',
        'user_id',
        'attachments',
        'admin_notes',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'attachments' => 'array',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the user who reported this issue (if authenticated)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who resolved this issue
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scope for filtering by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for filtering by priority
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Get the status badge color
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'open' => 'bg-red-100 text-red-800',
            'in_progress' => 'bg-yellow-100 text-yellow-800',
            'resolved' => 'bg-green-100 text-green-800',
            'closed' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get the priority badge color
     */
    public function getPriorityColorAttribute()
    {
        return match($this->priority) {
            'low' => 'bg-gray-100 text-gray-800',
            'medium' => 'bg-blue-100 text-blue-800',
            'high' => 'bg-orange-100 text-orange-800',
            'urgent' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get the type badge color
     */
    public function getTypeColorAttribute()
    {
        return match($this->type) {
            'bug' => 'bg-red-100 text-red-800',
            'feature_request' => 'bg-blue-100 text-blue-800',
            'improvement' => 'bg-green-100 text-green-800',
            'question' => 'bg-purple-100 text-purple-800',
            'other' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}
