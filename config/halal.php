<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Halal publish gate — the date it starts BLOCKING
    |--------------------------------------------------------------------------
    | Audit H-6. Products in a category flagged requires_halal_certificate need
    | an approved certificate to stay live. Until this date the policy reports
    | and does not block.
    |
    | Unset (the default) means report-only, forever, which is the correct
    | state for a catalogue whose sellers have not registered their
    | certificates yet. Turning it on with a date already in the past would
    | dark every uncertified food listing the moment the config cache rebuilds
    | — set a date far enough ahead that sellers can act on the nudge.
    |
    | ⚠ Read through config(), never env(), anywhere that runs under a cached
    | config — env() returns null there and this would silently read as "gate
    | off", the one outcome nobody investigates.
    */
    'gate_enforced_from' => env('HALAL_GATE_ENFORCED_FROM'),

];
