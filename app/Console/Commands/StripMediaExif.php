<?php

namespace App\Console\Commands;

use App\Support\PhotoMetadata;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * HB-B2 one-off cleanup: strip EXIF/GPS from image originals stored before
 * uploads were stripped (App\Listeners\StripPhotoMetadata). Dry run by
 * default: prints counts per collection only, never a path or a name.
 * --apply rewrites in place and lists the rewritten paths in storage/logs.
 * Files without metadata are skipped, so a re-run is safe.
 */
final class StripMediaExif extends Command
{
    protected $signature = 'media:strip-exif {--apply : Rewrite the files (default: dry run, counts only)}';

    protected $description = 'Count (dry run) or strip EXIF/GPS from stored image originals';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'APPLY: rewriting images that carry EXIF/XMP.' : 'DRY RUN: nothing is written. Re-run with --apply to strip.');

        $log = storage_path('logs/media-strip-exif-'.now()->format('Ymd-His').'.log');
        $rows = [];

        Media::query()->chunkById(200, function ($chunk) use (&$rows, $apply, $log) {
            foreach ($chunk as $media) {
                $key = class_basename($media->model_type).'/'.$media->collection_name;
                $rows[$key] ??= [$key, 0, 0, 0, 0, 0, 0];
                $rows[$key][1]++;

                try {
                    $path = $media->getPath();
                    if ($media->getDiskDriverName() !== 'local' || ! is_file($path)) {
                        $rows[$key][6]++;

                        continue;
                    }
                    $info = PhotoMetadata::inspect($path);
                    if (! $info) {
                        continue;
                    }
                    $rows[$key][2]++;
                    if (! $info['meta']) {
                        continue;
                    }
                    $rows[$key][3]++;
                    $rows[$key][4] += (int) $info['gps'];
                    if ($apply && PhotoMetadata::stripMedia($media)) {
                        $rows[$key][5]++;
                        file_put_contents($log, $path."\n", FILE_APPEND | LOCK_EX);
                    }
                } catch (Throwable) {
                    $rows[$key][6]++;
                }
            }
        });

        ksort($rows);
        $this->table(['Collection', 'Files', 'Images', 'With EXIF', 'With GPS', 'Rewritten', 'Errors'], array_values($rows));

        if (array_sum(array_column($rows, 5)) > 0) {
            $this->line('Rewritten paths: '.$log);
        }

        return self::SUCCESS;
    }
}
