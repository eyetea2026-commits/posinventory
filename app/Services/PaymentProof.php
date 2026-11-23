<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PaymentProof
{
    public const DISK = 'local';

    private const DIRECTORY = 'payment-proofs';

    private const MAX_SIDE = 1600;

    private const QUALITY = 80;

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
