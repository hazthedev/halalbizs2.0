<?php

return [

    // Master switch. When off, deals/teams are inert and checkout ignores
    // memberships entirely (no price change → existing checkout untouched).
    'enabled' => env('GROUPBUY_ENABLED', true),

    // Defaults the seller form starts from.
    'default_target_size' => (int) env('GROUPBUY_DEFAULT_TARGET', 2),
    'default_window_hours' => (int) env('GROUPBUY_DEFAULT_WINDOW_HOURS', 24),

];
