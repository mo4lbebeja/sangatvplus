<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Shrinks uploaded images so 1 GB-RAM TVs decode them quickly.
 * cover(): crop-to-fill an exact size (slides). fit(): limit the longest side (cards, logos).
 */
class ImageOptimizer
{
    public static function cover(string $disk, string $path, int $w, int $h, int $quality = 82): string
    {
        if (extension_loaded('imagick')) {
            return self::imagick($disk, $path, fn (\Imagick $im) => $im->cropThumbnailImage($w, $h), $quality);
        }

        return self::process($disk, $path, function ($src, $sw, $sh) use ($w, $h) {
            $scale = max($w / $sw, $h / $sh);
            $cw = (int) round($w / $scale);
            $ch = (int) round($h / $scale);
            $dst = imagecreatetruecolor($w, $h);
            imagecopyresampled($dst, $src, 0, 0, (int) (($sw - $cw) / 2), (int) (($sh - $ch) / 2), $w, $h, $cw, $ch);

            return $dst;
        }, $quality);
    }

    public static function fit(string $disk, string $path, int $max, int $quality = 82): string
    {
        if (extension_loaded('imagick')) {
            return self::imagick($disk, $path, function (\Imagick $im) use ($max) {
                if (max($im->getImageWidth(), $im->getImageHeight()) > $max) {
                    $im->thumbnailImage($max, $max, true);
                }
            }, $quality);
        }

        return self::process($disk, $path, function ($src, $sw, $sh) use ($max) {
            $scale = min(1, $max / max($sw, $sh));
            $w = max(1, (int) round($sw * $scale));
            $h = max(1, (int) round($sh * $scale));
            $dst = imagecreatetruecolor($w, $h);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);

            return $dst;
        }, $quality);
    }

    /** Writes a separate JPEG thumbnail (longest side = $max) next to the image; returns its path. */
    public static function thumbnail(string $disk, string $path, int $max = 480): ?string
    {
        $fs = Storage::disk($disk);
        $thumb = preg_replace('/(\.[^.\/]+)?$/', '', $path, 1).'-thumb.jpg';
        try {
            if (! $fs->copy($path, $thumb)) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }
        $result = self::fit($disk, $thumb, $max, 78);

        return $fs->exists($result) ? $result : null;
    }

    /** Imagick keeps big camera photos out of PHP's memory_limit. */
    private static function imagick(string $disk, string $path, callable $transform, int $quality): string
    {
        try {
            $fs = Storage::disk($disk);
            $im = new \Imagick;
            $im->readImageBlob($fs->get($path));
            $im->autoOrient(); // respect phone EXIF rotation
            $transform($im);
            $im->setImageBackgroundColor('white');
            $im = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $im->setImageFormat('jpeg');
            $im->setImageCompressionQuality($quality);
            $im->setInterlaceScheme(\Imagick::INTERLACE_PLANE);
            $im->stripImage(); // drop EXIF (GPS etc.)

            $newPath = preg_replace('/\.[^.\/]+$/', '', $path).'.jpg';
            $fs->put($newPath, $im->getImagesBlob());
            $im->clear();
            if ($newPath !== $path) {
                $fs->delete($path);
            }

            return $newPath;
        } catch (Throwable) {
            return $path;
        }
    }

    /** Returns the (possibly new, .jpg) path. Leaves the file untouched on any failure. */
    private static function process(string $disk, string $path, callable $transform, int $quality): string
    {
        try {
            $fs = Storage::disk($disk);
            $data = $fs->get($path);
            $src = $data !== null ? @imagecreatefromstring($data) : false;
            if ($src === false) {
                return $path;
            }
            $dst = $transform($src, imagesx($src), imagesy($src));
            ob_start();
            imageinterlace($dst, true);
            imagejpeg($dst, null, $quality);
            $jpeg = ob_get_clean();

            $newPath = preg_replace('/\.[^.\/]+$/', '', $path).'.jpg';
            $fs->put($newPath, $jpeg);
            if ($newPath !== $path) {
                $fs->delete($path);
            }

            return $newPath;
        } catch (Throwable) {
            return $path;
        }
    }
}
