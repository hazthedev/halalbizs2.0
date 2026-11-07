<?php

/*
| Mobile API token auth (mobile plan §1). Only the keys that differ from the
| package defaults live here; everything else keeps Sanctum's own default.
*/
return [

    // Token auth only. Sanctum's default guard list is ['web'], which also
    // accepts a browser session cookie on /api/* — and the api group carries
    // no CSRF check, so that would turn every state-changing endpoint into a
    // CSRF target for a logged-in browser. An empty list means bearer tokens
    // are the only way in.
    'guard' => [],

    // No SPA, so the /sanctum/csrf-cookie route has no caller.
    'routes' => false,

    // Minutes a token lives after issue (60 days). The app signs in again on
    // the 401; `sanctum:prune-expired` deletes the rows a day later.
    'expiration' => (int) env('SANCTUM_EXPIRATION', 60 * 24 * 60),

];
