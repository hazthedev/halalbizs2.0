<?php

use App\Models\User;
use App\Notifications\NewDeviceLoginNotification;
use Illuminate\Notifications\Messages\MailMessage;

test('every markdown mail inherits the HalalBizs header and footer', function () {
    $html = (string) (new MailMessage)
        ->subject('Layout check')
        ->line('Body line for the layout snapshot.')
        ->render();

    expect($html)
        ->toContain('HalalBizs')                                              // wordmark
        ->toContain('#1A1714')                                                // ink header band
        ->toContain('you have a HalalBizs account')                           // footer line
        ->toContain('© '.date('Y'))
        ->toContain('Body line for the layout snapshot.');
});

test('a real notification mail carries the brand', function () {
    $user = User::factory()->create();

    $html = (string) (new NewDeviceLoginNotification('Chrome on Windows', '203.0.113.7'))
        ->toMail($user)
        ->render();

    expect($html)
        ->toContain('HalalBizs')
        ->toContain('Chrome on Windows')
        ->toContain('Review account security');
});
