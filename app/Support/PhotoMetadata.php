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
     * @return array{type: string, meta: bool, gps: bool, orientation: int, icc: list<string>, blocks: list<array{int, int}>, sos: ?int}|null
     *                                                                                              null = not a JPEG/PNG/WebP
     */
    public static function inspect(string $path): ?array
    {
        $b = (string) @file_get_contents($path);
        $tiffs = $other = $icc = $blocks = [];
        $sos = null;

        if (str_starts_with($b, "\xFF\xD8")) {
            $type = 'jpeg';
            for ($i = 2, $n = strlen($b); $i + 4 <= $n && $b[$i] === "\xFF";) {
                $m = ord($b[$i + 1]);
                if ($m === 0xFF || $m === 0x01 || ($m >= 0xD0 && $m <= 0xD8)) { // fill byte / standalone marker
                    $i += $m === 0xFF ? 1 : 2;

                    continue;
                }
                $len = unpack('n', substr($b, $i + 2, 2))[1];
                if ($m === 0xDA || $m === 0xD9 || $len < 2) { // image data starts (no metadata after it), or a broken length
                    $sos = $m === 0xDA ? $i : null;

                    break;
                }
                $data = substr($b, $i + 4, $len - 2);
                match (true) {
                    $m === 0xE1 && str_starts_with($data, "Exif\0\0") => $tiffs[] = substr($data, 6),
                    $m === 0xE1, $m === 0xED => $other[] = $data, // XMP; APP13 IPTC (can name a place)
                    $m === 0xE2 && str_starts_with($data, "ICC_PROFILE\0") => $icc[] = substr($b, $i, $len + 2),
                    default => null,
                };
                if ($m === 0xE1 || $m === 0xED) {
                    $blocks[] = [$i, $len + 2]; // where stripJpegBytes() cuts
                }
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

        return ['type' => $type, 'meta' => $tiffs !== [] || $other !== [], 'gps' => $gps, 'orientation' => $orientation, 'icc' => $icc, 'blocks' => $blocks, 'sos' => $sos];
    }

    /** Rewrite the file without metadata, upright. False = nothing to strip (or not a JPEG/PNG/WebP). */
    public static function strip(string $path): bool
    {
        $info = self::inspect($path);
        if (! $info || ! $info['meta']) {
            return false;
        }

        $img = self::fitsInMemory($path, $info['orientation']) ? @imagecreatefromstring((string) file_get_contents($path)) : false;
        if ($img === false && $info['type'] === 'jpeg' && $info['sos'] !== null) {
            return self::stripJpegBytes($path, $info);
        }
        if ($img === false) {
            throw new RuntimeException('GD cannot decode this image (animated WebP?) or it is too big to decode');
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
        if ($info['icc'] !== [] && substr($info['icc'][0], 34, 4) === 'RGB ') { // a CMYK/grey profile would mis-colour GD's RGB output
            $out = (string) file_get_contents($tmp);
            $at = substr($out, 2, 2) === "\xFF\xE0" ? 4 + unpack('n', substr($out, 4, 2))[1] : 2; // after JFIF APP0
            file_put_contents($tmp, substr($out, 0, $at).implode('', $info['icc']).substr($out, $at));
        }

        @chmod($tmp, fileperms($path) & 0777);
        rename($tmp, $path); // same directory: atomic, never a half-written original

        return true;
    }

    /**
     * GD holds 4 bytes a pixel, twice while turning upright. Running out of
     * memory_limit is a fatal error no catch can stop (a 24 MP iPhone photo
     * needs about 220 MB), so a photo that would not fit is not decoded.
     */
    private static function fitsInMemory(string $path, int $orientation): bool
    {
        $limit = ini_parse_quantity((string) ini_get('memory_limit'));
        [$w, $h] = @getimagesize($path) ?: [0, 0];

        return $limit <= 0 || memory_get_usage() + $w * $h * (in_array($orientation, [3, 5, 6, 7, 8], true) ? 10 : 5) + filesize($path) * 2 < $limit;
    }

    /**
     * JPEG too big (or too odd) for GD: cut the EXIF/XMP/IPTC blocks out of the
     * bytes, no re-encode. Only an Orientation tag goes back in, so the photo
     * still shows upright; anything after the image's end marker (phone
     * extras such as depth maps, which carry their own EXIF) is dropped.
     */
    private static function stripJpegBytes(string $path, array $info): bool
    {
        $b = (string) file_get_contents($path);
        $end = strpos($b, "\xFF\xD9", $info['sos']);
        $b = substr($b, 0, $end === false ? null : $end + 2);
        foreach (array_reverse($info['blocks']) as [$at, $len]) {
            $b = substr_replace($b, '', $at, $len);
        }
        $app1 = $info['orientation'] === 1 ? '' // TIFF: one IFD with one SHORT entry, Orientation
            : "\xFF\xE1\x00\x22Exif\0\0II*\0\x08\0\0\0\x01\0\x12\x01\x03\0\x01\0\0\0".pack('v', $info['orientation'])."\0\0\0\0\0\0";

        $tmp = $path.'.strip';
        file_put_contents($tmp, "\xFF\xD8".$app1.substr($b, 2));
        @chmod($tmp, fileperms($path) & 0777);
        rename($tmp, $path);

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
