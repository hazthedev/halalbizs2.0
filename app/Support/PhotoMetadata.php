<?php

namespace App\Support;

use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Privacy (HB-B2, PDPA): a phone photo's EXIF/XMP can hold the GPS position it
 * was taken at, often a home. Strip = decode with GD, turn the pixels upright
 * from the EXIF Orientation tag, re-encode (GD writes no metadata). Same file,
 * name and format; JPEG keeps its colour (ICC) profile.
 *
 * Metadata is read by walking the file's own blocks, not with the exif
 * extension, so orientation never depends on a server extension.
 * Only JPEG, PNG and WebP are touched; GIF, BMP, HEIC, PDF and video return
 * null and stay byte for byte (no upload rule accepts HEIC).
 */
final class PhotoMetadata
{
    private const QUALITY = 88;

    /**
     * @return array{type: string, meta: bool, gps: bool, orientation: int, icc: list<string>}|null
     *                                                                                              null = not a JPEG/PNG/WebP
     */
    public static function inspect(string $path): ?array
    {
        $b = (string) @file_get_contents($path);
        $tiffs = $other = $icc = [];

        if (str_starts_with($b, "\xFF\xD8")) {
            $type = 'jpeg';
            for ($i = 2, $n = strlen($b); $i + 4 <= $n && $b[$i] === "\xFF";) {
                $m = ord($b[$i + 1]);
                if ($m === 0xFF || $m === 0x01 || ($m >= 0xD0 && $m <= 0xD8)) { // fill byte / standalone marker
                    $i += $m === 0xFF ? 1 : 2;

                    continue;
                }
                if ($m === 0xDA || $m === 0xD9) { // image data starts: no metadata after it
                    break;
                }
                $len = unpack('n', substr($b, $i + 2, 2))[1];
                $data = substr($b, $i + 4, $len - 2);
                match (true) {
                    $m === 0xE1 && str_starts_with($data, "Exif\0\0") => $tiffs[] = substr($data, 6),
                    $m === 0xE1, $m === 0xED => $other[] = $data, // XMP; APP13 IPTC (can name a place)
                    $m === 0xE2 && str_starts_with($data, "ICC_PROFILE\0") => $icc[] = substr($b, $i, $len + 2),
                    default => null,
                };
                $i += 2 + $len;
            }
        } elseif (str_starts_with($b, "\x89PNG\r\n\x1A\n")) {
            $type = 'png';
            for ($i = 8, $n = strlen($b); $i + 8 <= $n;) {
                $len = unpack('N', substr($b, $i, 4))[1];
                $chunk = substr($b, $i + 4, 4);
                $data = substr($b, $i + 8, $len);
                match (true) {
                    $chunk === 'eXIf' => $tiffs[] = $data,
                    in_array($chunk, ['tEXt', 'zTXt', 'iTXt'], true)
                        && (str_starts_with($data, 'XML:com.adobe.xmp') || str_starts_with($data, 'Raw profile type')) => $other[] = $data,
                    default => null,
                };
                if ($chunk === 'IEND') {
                    break;
                }
                $i += 12 + $len;
            }
        } elseif (str_starts_with($b, 'RIFF') && substr($b, 8, 4) === 'WEBP') {
            $type = 'webp';
            for ($i = 12, $n = strlen($b); $i + 8 <= $n;) {
                $chunk = substr($b, $i, 4);
                $len = unpack('V', substr($b, $i + 4, 4))[1];
                $data = substr($b, $i + 8, $len);
                match ($chunk) {
                    'EXIF' => $tiffs[] = str_starts_with($data, "Exif\0\0") ? substr($data, 6) : $data,
                    'XMP ' => $other[] = $data,
                    default => null,
                };
                $i += 8 + $len + ($len % 2);
            }
        } else {
            return null;
        }

        $orientation = 1;
        $gps = false;
        foreach ($tiffs as $tiff) {
            [$o, $g] = self::tiff($tiff);
            $orientation = $orientation === 1 ? $o : $orientation;
            $gps = $gps || $g;
        }
        foreach ($other as $data) {
            $gps = $gps || str_contains($data, 'GPSLatitude');
        }

        return ['type' => $type, 'meta' => $tiffs !== [] || $other !== [], 'gps' => $gps, 'orientation' => $orientation, 'icc' => $icc];
    }

    /** Rewrite the file without metadata, upright. False = nothing to strip (or not a JPEG/PNG/WebP). */
    public static function strip(string $path): bool
    {
        $info = self::inspect($path);
        if (! $info || ! $info['meta']) {
            return false;
        }

        $img = @imagecreatefromstring((string) file_get_contents($path));
        if ($img === false) {
            throw new RuntimeException('GD cannot decode this image (animated WebP?)');
        }
        imagepalettetotruecolor($img);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $img = self::upright($img, $info['orientation']);

        $tmp = $path.'.strip';
        $ok = match ($info['type']) {
            'jpeg' => imagejpeg($img, $tmp, self::QUALITY),
            'png' => imagepng($img, $tmp),
            'webp' => imagewebp($img, $tmp, self::QUALITY),
        };
        if (! $ok) {
            @unlink($tmp);
            throw new RuntimeException('GD could not write the image');
        }

        // ponytail: ICC kept for JPEG only (phone photos); PNG/WebP lose theirs, add when colours look off.
        if ($info['icc'] !== []) {
            $out = (string) file_get_contents($tmp);
            $at = substr($out, 2, 2) === "\xFF\xE0" ? 4 + unpack('n', substr($out, 4, 2))[1] : 2; // after JFIF APP0
            file_put_contents($tmp, substr($out, 0, $at).implode('', $info['icc']).substr($out, $at));
        }

        rename($tmp, $path); // same directory: atomic, never a half-written original

        return true;
    }

    /**
     * Strip a stored media original in place; only the size column changes
     * (no updated_at, no model events). Local-driver disks only.
     */
    public static function stripMedia(Media $media): bool
    {
        // ponytail: local disks only (live uses public + local); an S3 disk needs download, strip, re-upload.
        if ($media->getDiskDriverName() !== 'local' || ! self::strip($path = $media->getPath())) {
            return false;
        }

        clearstatcache(true, $path);
        $media->size = (int) filesize($path);
        $media->newQuery()->whereKey($media->getKey())->toBase()->update(['size' => $media->size]);

        return true;
    }

    /** @return array{0: int, 1: bool} EXIF Orientation (1 = upright) and whether a GPS position is present. */
    private static function tiff(string $t): array
    {
        $le = str_starts_with($t, 'II');
        $u16 = fn (int $o) => $o >= 0 && strlen($t) >= $o + 2 ? unpack($le ? 'v' : 'n', substr($t, $o, 2))[1] : 0;
        $u32 = fn (int $o) => $o >= 0 && strlen($t) >= $o + 4 ? unpack($le ? 'V' : 'N', substr($t, $o, 4))[1] : 0;
        // tag => offset of its value field
        $tags = function (int $ifd) use ($u16): array {
            $out = [];
            for ($k = 0, $count = $ifd > 0 ? $u16($ifd) : 0; $k < $count; $k++) {
                $out[$u16($ifd + 2 + 12 * $k)] = $ifd + 10 + 12 * $k;
            }

            return $out;
        };

        $ifd0 = $tags($u32(4));
        $orientation = isset($ifd0[0x0112]) ? $u16($ifd0[0x0112]) : 1;
        $gps = isset($ifd0[0x8825]) && isset($tags($u32($ifd0[0x8825]))[0x0002]); // GPS IFD with a latitude

        return [$orientation >= 1 && $orientation <= 8 ? $orientation : 1, $gps];
    }

    /** Apply EXIF orientation to the pixels (GD rotates counter-clockwise). */
    private static function upright(\GdImage $img, int $orientation): \GdImage
    {
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($img, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        }

        $angle = match ($orientation) {
            3 => 180, 5, 8 => 90, 6, 7 => 270,
            default => 0,
        };

        return $angle === 0 ? $img : imagerotate($img, $angle, 0);
    }
}
