<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;

class ImageOptimizer
{
    /**
     * Convert JPEG/PNG uploads to a resized WebP image when GD supports it.
     * Returns null when conversion is unavailable or unsafe; callers should keep the original.
     */
    public function toWebp(UploadedFile $file, int $maxDimension = 2560, int $quality = 82): ?string
    {
        if (!in_array(strtolower((string) $file->getMimeType()), ['image/jpeg', 'image/png'], true)
            || !function_exists('imagecreatefromstring')
            || !function_exists('imagewebp')) {
            return null;
        }

        $dimensions = @getimagesize($file->getRealPath());
        if (!is_array($dimensions) || empty($dimensions[0]) || empty($dimensions[1])
            || ((int) $dimensions[0] * (int) $dimensions[1]) > 40000000) {
            return null;
        }

        $bytes = @file_get_contents($file->getRealPath());
        if (!is_string($bytes) || $bytes === '') {
            return null;
        }

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        if ($width < 1 || $height < 1 || ($width * $height) > 40000000) {
            imagedestroy($source);
            return null;
        }

        $scale = min(1, $maxDimension / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        if (strtolower((string) $file->getMimeType()) === 'image/png') {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        $success = imagewebp($target, null, max(50, min(90, $quality)));
        $webp = ob_get_clean();

        imagedestroy($source);
        imagedestroy($target);

        return $success && is_string($webp) && str_starts_with($webp, 'RIFF') ? $webp : null;
    }
}
