<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Local/dev SMS driver — writes the message to the log instead of sending.
 * A real Malaysian gateway driver slots in behind the SmsSender interface
 * later, keyed by the (already stored, encrypted) sms_provider_key setting.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info("SMS to {$phone}: {$message}");
    }
}
