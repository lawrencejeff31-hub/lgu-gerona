<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Signature extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'user_id',
        'signature_image_path',
        'signature_file_path',
        'signature_hash',
        'certificate_serial',
        'algorithm',
        'metadata',
        'signed_at',
        'pnpki_certificate',
        'verification_status',
        'signature_type'
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'metadata' => 'array',
        'pnpki_certificate' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function isPNPKI(): bool
    {
        return $this->signature_type === 'pnpki';
    }
    
    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }
}