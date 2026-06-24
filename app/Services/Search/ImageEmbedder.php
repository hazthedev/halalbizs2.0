<?php

namespace App\Services\Search;

/**
 * Deterministic image embedder (M2.3 visual search): a 4×4×4 RGB colour
 * histogram (64 bins), L2-normalised, computed via GD over a downsampled grid.
 * It captures dominant colours/palette so visually similar products rank
 * together — the offline fallback; a real vision model can replace it.
 */
class ImageEmbedder
{
    private const BINS = 64;

    /**
     * @return array<int, float>|null null when the path is unreadable / GD missing
     */
    public function embed(?string $path): ?array
    {
        if ($path === null || ! is_file($path) || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $raw = @file_get_contents($path);
        $image = $raw === false ? false : @imagecreatefromstring($raw);

        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $bins = array_fill(0, self::BINS, 0.0);
        $stepX = max(1, intdiv($width, 48));
        $stepY = max(1, intdiv($height, 48));
        $sampled = 0;

        for ($x = 0; $x < $width; $x += $stepX) {
            for ($y = 0; $y < $height; $y += $stepY) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $bins[intdiv($r, 64) * 16 + intdiv($g, 64) * 4 + intdiv($b, 64)] += 1.0;
                $sampled++;
            }
        }

        imagedestroy($image);

        if ($sampled === 0) {
            return null;
        }

        return $this->normalize($bins);
    }

    /**
     * @param  array<int, float>  $vector
     * @return array<int, float>
     */
    private function normalize(array $vector): array
    {
        $norm = sqrt(array_sum(array_map(fn (float $x) => $x * $x, $vector)));

        return $norm <= 0.0 ? $vector : array_map(fn (float $x) => $x / $norm, $vector);
    }
}
