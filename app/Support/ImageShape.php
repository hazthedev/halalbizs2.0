<?php

namespace App\Support;

use Closure;
use Illuminate\Http\UploadedFile;

/**
 * CMS guard rail: a picture slot has one shape. A wrong-shaped or tiny upload
 * is refused with a plain message, so it cannot be cropped badly or stretched
 * on the storefront.
 */
final class ImageShape
{
    /** Home banner: drawn 3:1 (aspect-[3/1]); 1280×400 from the older hint still passes. */
    public static function banner(): Closure
    {
        return self::rule(1200, 2.8, 3.4, __('Use a wide picture, about 3 times as wide as it is tall and at least 1200 pixels wide (for example 1500 × 500).'));
    }

    /** Occasion hero: a full-width strip, cropped to fit, so any clearly wide picture works. */
    public static function hero(): Closure
    {
        return self::rule(1200, 1.5, 6.0, __('Use a wide picture at least 1200 pixels wide (for example 1600 × 600).'));
    }

    /** Validation rule: at least $minWidth px wide, width ÷ height within the two ratios. */
    private static function rule(int $minWidth, float $minRatio, float $maxRatio, string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($minWidth, $minRatio, $maxRatio, $message) {
            $size = $value instanceof UploadedFile ? @getimagesize($value->getRealPath()) : false;

            // Not a readable picture: the `image` rule beside this one reports it.
            if ($size === false || $size[1] === 0) {
                return;
            }

            $ratio = $size[0] / $size[1];

            if ($size[0] < $minWidth || $ratio < $minRatio || $ratio > $maxRatio) {
                $fail($message);
            }
        };
    }
}
