<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Delivers the phone-verification code over WhatsApp (Meta Cloud API) instead
 * of SMS — WhatsApp is how Malaysians receive OTPs and Meta's authentication
 * templates have a genuine free tier, so no per-message SMS cost.
 *
 * Bound in place of LogSmsSender only when the Cloud API is configured (token +
 * phone-number id), exactly like the mail log→SMTP and iPay88 mock→live
 * cutovers. Unconfigured environments keep logging, so nothing breaks before
 * the Meta credentials are in place.
 *
 * Business-initiated WhatsApp messages MUST use an approved template, so this
 * sends the "authentication" category template with the code as its parameter —
 * the raw code is lifted from the OTP message (which always contains exactly one
 * 6-digit code) to keep the shared SmsSender contract unchanged.
 * ponytail: regex-extract the code rather than widen the interface for one caller.
 */
class WhatsAppSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        $code = $this->extractCode($message);

        if ($code === null) {
            // No code in the payload — never silently drop a verification.
            Log::warning('WhatsAppSender: no 6-digit code found in message, not sent.');

            return;
        }

        $config = config('services.whatsapp');

        $response = Http::withToken($config['token'])
            ->post("https://graph.facebook.com/{$config['version']}/{$config['phone_number_id']}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $this->normalise($phone),
                'type' => 'template',
                'template' => [
                    'name' => $config['template'],
                    'language' => ['code' => $config['template_lang']],
                    'components' => [
                        // Meta's authentication template: the code fills the body
                        // and the one-tap "copy code" button (same value, index 0).
                        ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]],
                        ['type' => 'button', 'sub_type' => 'url', 'index' => '0',
                            'parameters' => [['type' => 'text', 'text' => $code]]],
                    ],
                ],
            ]);

        if ($response->failed()) {
            // Surface the failure — the caller (OtpService) already returned the
            // code as "sent", so a silent drop would strand the user.
            Log::error('WhatsAppSender: Cloud API send failed', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);
        }
    }

    /** Malaysian numbers to E.164 without the '+': 0123456789 → 60123456789. */
    private function normalise(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '60')) {
            return $digits;
        }

        return '60'.ltrim($digits, '0');
    }

    private function extractCode(string $message): ?string
    {
        return preg_match('/\b(\d{6})\b/', $message, $m) ? $m[1] : null;
    }
}
