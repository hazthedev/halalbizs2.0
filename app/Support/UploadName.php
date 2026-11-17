<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Stored filename with a SERVER-DETECTED extension (AL-M2, security
 * audit). Media-library only blocks PHP-family extensions, not e.g.
 * .html/.htm, and MEDIA_DISK defaults to the public disk — trusting the
 * client's filename would let an image-content polyglot uploaded as
 * "x.html" be served back as text/html from the app's own origin.
 */
final class UploadName
{
    public static function safe(UploadedFile $file, string $prefix = ''): string
    {
        $extension = $file->guessExtension() ?: 'bin';
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        return $prefix.($base !== '' ? $base : 'photo').'.'.$extension;
    }

    /**
     * Random 40-char name for private evidence (return photos) that is still
     * served by direct URL from the public disk: the URL is unguessable
     * instead of {media id}/{client file name}.
     */
    public static function unguessable(UploadedFile $file, string $prefix = ''): string
    {
        return $prefix.Str::random(40).'.'.($file->guessExtension() ?: 'bin');
    }
}
