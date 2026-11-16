<?php

namespace App\Http\Middleware;

use Illuminate\Session\Middleware\AuthenticateSession;

class AuthenticateBrowserSession extends AuthenticateSession
{
    protected function storePasswordHashInSession($request)
    {
        // Pre-upgrade sessions have no password proof. Require a fresh login
        // rather than blessing a possibly compromised session after recovery.
        if (! $request->session()->has('password_hash_'.$this->auth->getDefaultDriver())
            && $request->session()->has($this->guard()->getName())) {
            $this->logout($request);
        }

        parent::storePasswordHashInSession($request);
    }
}
