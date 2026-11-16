<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class StoreBrowserPasswordHash
{
    public function handle(Login $event): void
    {
        if ($event->guard === 'web') {
            session()->put('password_hash_web', $event->user->getAuthPassword());
        }
    }
}
