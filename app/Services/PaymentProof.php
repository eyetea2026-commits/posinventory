<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Proof of payment photos (GCash screenshot, bank transfer slip, cheque).
 * Kept readable (up to 1600 px) and on the PRIVATE disk: they show account
 * names and numbers, so they're only served to signed-in staff through
 * PaymentProofController, never by a public link.
 */
class PaymentProof
{
    public const DISK = 'local';

    private const DIRECTORY = 'payment-proofs';

    private const MAX_SIDE = 1600;

    private const QUALITY = 80;

    /**
     * Payment methods a proof can be attached to.
     *
     * @var list<string>
     */
    public const METHODS = ['gcash', 'bank', 'cheque'];

    public static function store(UploadedFile $file): string
    {
        return ProductImage::shrinkAndStore($file->getRealPath(), self::DISK, self::DIRECTORY, self::MAX_SIDE, self::QUALITY);
    }

    public static function delete(?string $relative): void
    {
        if ($relative) {
            Storage::disk(self::DISK)->delete($relative);
        }
    }
}
