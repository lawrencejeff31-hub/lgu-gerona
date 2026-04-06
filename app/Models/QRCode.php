<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QRCode extends Model
{
    use HasFactory;

    protected $table = 'qr_codes'; // Use the correct table name

    protected $fillable = [
        'document_id',
        'token',
        'qr_image_path',
        'scan_count',
        'last_scanned_at',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'last_scanned_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}