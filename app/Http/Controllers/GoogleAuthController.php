<?php

namespace App\Http\Controllers;

// Placeholder - replaced by the auth agent (Google OAuth via Socialite).
class GoogleAuthController extends Controller
{
    public function redirect()
    {
        return redirect()->route('login');
    }

    public function callback()
    {
        return redirect()->route('login');
    }
}
