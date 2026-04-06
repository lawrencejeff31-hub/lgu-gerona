<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Signature;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * PNPKI Digital Signature Service
 * 
 * This service integrates with the Philippine National Public Key Infrastructure (PNPKI)
 * for digital signature functionality. In production, this would connect to the actual
 * PNPKI API. For development, it provides a mock implementation.
 */
class PNPKISignatureService
{
    /**
     * Sign a document using PNPKI digital signature
     *
     * @param Document $document
     * @param User $user
     * @param array $signatureData Additional signature data (certificate, PIN, etc.)
     * @return Signature
     * @throws \Exception
     */
    public function signDocument(Document $document, User $user, array $signatureData = []): Signature
    {
        try {
            // In production, this would:
            // 1. Validate user's PNPKI certificate
            // 2. Create a hash of the document
            // 3. Sign the hash using PNPKI API
            // 4. Store the signature file (.sig)
            
            // Mock implementation for development
            $signatureHash = $this->generateSignatureHash($document, $user);
            $signatureFile = $this->createSignatureFile($document, $signatureHash);
            
            // Create signature record
            $signature = Signature::create([
                'document_id' => $document->id,
                'user_id' => $user->id,
                'signature_hash' => $signatureHash,
                'signature_file_path' => $signatureFile,
                'certificate_serial' => $signatureData['certificate_serial'] ?? $this->mockCertificateSerial(),
                'signed_at' => now(),
                'algorithm' => 'SHA256withRSA', // PNPKI standard
                'metadata' => [
                    'signer_name' => $user->name,
                    'signer_email' => $user->email,
                    'department' => $user->department->name ?? 'N/A',
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]
            ]);

            // Log the signing action
            Log::info('Document signed', [
                'document_id' => $document->id,
                'user_id' => $user->id,
                'signature_id' => $signature->id
            ]);

            return $signature;

        } catch (\Exception $e) {
            Log::error('Document signing failed', [
                'document_id' => $document->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Verify a document signature
     *
     * @param Signature $signature
     * @return bool
     */
    public function verifySignature(Signature $signature): bool
    {
        try {
            // Basic guards: ensure we have a signature file path and signed_at
            if (empty($signature->signature_file_path)) {
                // No file path to verify against
                return false;
            }

            // In production, this would:
            // 1. Retrieve the signature file
            // 2. Validate against PNPKI certificate authority
            // 3. Check certificate revocation status
            // 4. Verify the document hash matches
            
            // Mock implementation
            // We store .sig files on the 'public' disk; normalize and check existence there
            $relativePath = ltrim($signature->signature_file_path, '/');
            if (str_starts_with($relativePath, 'storage/')) {
                // Some records may include the 'storage/' prefix; trim it for disk-relative lookups
                $relativePath = substr($relativePath, strlen('storage/'));
            }

            if (!Storage::disk('public')->exists($relativePath)) {
                return false;
            }

            // Check if signature is not expired (valid for 5 years in PNPKI)
            $signedAt = $signature->signed_at;
            if (!$signedAt) {
                return false;
            }
            $expiryDate = $signedAt->addYears(5);
            
            if (now()->greaterThan($expiryDate)) {
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Signature verification failed', [
                'signature_id' => $signature->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Generate a signature hash for the document
     *
     * @param Document $document
     * @param User $user
     * @return string
     */
    private function generateSignatureHash(Document $document, User $user): string
    {
        // Create a unique hash based on document content and user
        $fileHash = null;
        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            $fileHash = hash_file('sha256', Storage::disk('public')->path($document->file_path));
        }

        $data = json_encode([
            'document_id' => $document->id,
            'document_number' => $document->document_number,
            'user_id' => $user->id,
            'timestamp' => now()->toIso8601String(),
            'file_hash' => $fileHash
        ]);

        return hash('sha256', $data);
    }

    /**
     * Create a signature file (.sig)
     *
     * @param Document $document
     * @param string $signatureHash
     * @return string File path
     */
    private function createSignatureFile(Document $document, string $signatureHash): string
    {
        $filename = "signature_{$document->id}_" . time() . ".sig";
        $path = "signatures/{$filename}";

        // In production, this would contain the actual PNPKI signature data
        // For now, we store a mock signature file
        $signatureContent = json_encode([
            'version' => '1.0',
            'algorithm' => 'SHA256withRSA',
            'signature_hash' => $signatureHash,
            'timestamp' => now()->toIso8601String(),
            'issuer' => 'Philippine National Public Key Infrastructure',
        ], JSON_PRETTY_PRINT);

        // Store on the 'public' disk to align with verification checks
        Storage::disk('public')->put($path, $signatureContent);

        return $path;
    }

    /**
     * Generate a mock certificate serial number
     *
     * @return string
     */
    private function mockCertificateSerial(): string
    {
        return 'PNPKI-' . strtoupper(bin2hex(random_bytes(8)));
    }

    /**
     * Get signature details for display
     *
     * @param Signature $signature
     * @return array
     */
    public function getSignatureDetails(Signature $signature): array
    {
        return [
            'id' => $signature->id,
            'signer' => $signature->user->name,
            'department' => $signature->user->department->name ?? 'N/A',
            'signed_at' => $signature->signed_at->format('Y-m-d H:i:s'),
            'certificate_serial' => $signature->certificate_serial,
            'algorithm' => $signature->algorithm,
            'is_valid' => $this->verifySignature($signature),
            'metadata' => $signature->metadata,
        ];
    }

    /**
     * Check if user has valid PNPKI certificate
     * In production, this would validate against PNPKI registry
     *
     * @param User $user
     * @return bool
     */
    public function hasValidCertificate(User $user): bool
    {
        // Mock implementation - in production, check PNPKI registry
        return true;
    }
}
