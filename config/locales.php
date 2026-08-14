<?php

/**
 * Display names for the UI locales, in their OWN language — someone hunting for
 * Vietnamese is looking for "Tiếng Việt", whatever the site happens to be
 * showing right now. `short` is the header pill label.
 *
 * This file only NAMES the locales. Which ones are actually offered is
 * GeneralSettings::$enabled_locales, toggled on Admin → Localization.
 *
 * Adding a locale = a row here + a lang/<code>.json + enabling it in settings.
 * The switchers loop over this, so none of them need editing again.
 */
return [
    'en' => ['name' => 'English', 'short' => 'EN'],
    'ms' => ['name' => 'Bahasa Melayu', 'short' => 'BM'],
    'vi' => ['name' => 'Tiếng Việt', 'short' => 'VI'],
];
