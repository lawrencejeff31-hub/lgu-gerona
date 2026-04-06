<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'prefix',
        'requires_approval',
        'is_active',
        'schema'
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'is_active' => 'boolean',
        'schema' => 'array',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(DocumentTypeRoute::class)->orderBy('sequence');
    }
}