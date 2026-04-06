<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'user_id',
        'from_office_id',
        'to_office_id',
        'action',
        'description',
        'remarks',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromOffice(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_office_id');
    }

    public function toOffice(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_office_id');
    }
}