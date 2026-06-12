<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        NewsletterSubscriber::firstOrCreate(
            ['email' => $data['email']],
            ['user_id' => $request->user()?->id, 'verified_at' => now()],
        );

        return back()->with('newsletter.status', __('You’re subscribed. Thanks!'));
    }
}
