<?php

return [

    // Filesystem disk backups are written to. Point this at R2/S3 in production
    // (docs/10 — private bucket, signed URLs only).
    'disk' => env('BACKUP_DISK', 'local'),

    // Folder on the disk.
    'path' => 'backups',

    // backup:clean removes backups older than this many days.
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),

];
