<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTypeRoute extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_type_id',
        'department_id',
        'sequence',
        'is_required'
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
