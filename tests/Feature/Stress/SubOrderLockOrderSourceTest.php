<?php

use Illuminate\Support\Facades\File;

/*
 * Gate R3 on #225: every transaction that locks a sub-order takes its parent
 * order row first (parent order, then sub-orders, then variants), so a cancel
 * holding the parent can never cross a caller holding the sub-order (InnoDB
 * 1213). Runs on any driver: it reads the source, so a new direct sub-order
 * lock fails here before it can deadlock on MySQL.
 */
test('no code locks a sub-order row without going through SubOrder::lockForUpdateWithOrder', function () {
    $offenders = collect(File::allFiles(app_path()))
        ->filter(fn ($file) => preg_match('/SubOrder::whereKey\([^;]*->lockForUpdate\(\)/', $file->getContents()))
        ->map(fn ($file) => str_replace(base_path().'/', '', $file->getPathname()))
        ->reject(fn ($path) => $path === 'app/Models/SubOrder.php')
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
