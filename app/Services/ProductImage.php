<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Saves product photos small: every upload is scaled down to fit
 * MAX_SIDE x MAX_SIDE pixels and re-encoded as WebP (JPEG where the server
 * has no WebP support), so a phone photo of several MB is stored as a file
 * of roughly 10-40 KB. Files live on the public disk under products/.
 */
class ProductImage
{
    /** Longest side, in pixels, of a stored product photo. */
    public const MAX_SIDE = 480;

    private const QUALITY = 72;

    private const DIRECTORY = 'products';

    /**
     * Shrinks and stores an uploaded photo; returns its path on the public disk.
     */
    public static function store(UploadedFile $file): string
    {
        return self::storeFromPath($file->getRealPath());
    }

    /**
     * Shrinks and stores an image file from disk (used by the bulk photo
     * import); returns its path on the public disk.
     */
    public static function storeFromPath(string $path): string
    {
        return self::shrinkAndStore($path, 'public', self::DIRECTORY, self::MAX_SIDE, self::QUALITY);
    }

    /**
     * Scales an image down to fit $maxSide x $maxSide, re-encodes it (WebP,
     * else JPEG) and stores it on $disk under $directory; returns its path.
     * Shared with payment proofs (PaymentProof), which are kept larger and
     * on the private disk.
     */
    public static function shrinkAndStore(string $path, string $disk, string $directory, int $maxSide, int $quality): string
    {
        $contents = @file_get_contents($path);
        $source = $contents === false ? false : @imagecreatefromstring($contents);
        if ($source === false) {
            throw new RuntimeException('The file is not a readable image.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxSide / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        // White background, so transparent PNGs don't turn black.
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($resized, 0, 0, imagecolorallocate($resized, 255, 255, 255));
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($source);

        $useWebp = function_exists('imagewebp');
        ob_start();
        $useWebp ? imagewebp($resized, null, $quality) : imagejpeg($resized, null, $quality);
        $encoded = (string) ob_get_clean();
        imagedestroy($resized);

        $relative = $directory.'/'.Str::uuid().($useWebp ? '.webp' : '.jpg');
        Storage::disk($disk)->put($relative, $encoded);

        return $relative;
    }

    public static function delete(?string $relative): void
    {
        if ($relative) {
            Storage::disk('public')->delete($relative);
        }
    }

    public static function url(?string $relative): ?string
    {
        return $relative ? Storage::disk('public')->url($relative) : null;
    }
}
