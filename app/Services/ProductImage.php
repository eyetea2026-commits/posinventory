<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProductImage
{
    public const MAX_SIDE = 480;

    private const QUALITY = 72;

    private const DIRECTORY = 'products';

    public static function store(UploadedFile $file): string
    {
        return self::storeFromPath($file->getRealPath());
    }

    public static function storeFromPath(string $path): string
    {
        return self::shrinkAndStore($path, 'public', self::DIRECTORY, self::MAX_SIDE, self::QUALITY);
    }

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
