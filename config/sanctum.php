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

    // Tokens live until the app logs out or the account is deleted.
    'expiration' => null,

];
