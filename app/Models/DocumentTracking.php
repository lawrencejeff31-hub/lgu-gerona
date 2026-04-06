<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTracking extends Model
{
    // Explicitly set the table to match the migration name
    protected $table = 'document_tracking';

    protected $fillable = [
        'document_id',
        'user_id',
        'action',
        'notes',
        'changes',
        'action_date',
    ];

    protected $casts = [
        'changes' => 'array',
        'action_date' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
