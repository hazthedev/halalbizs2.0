<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin, config-gated wrapper over the Anthropic Messages API (M2.0 shared
 * foundation). One place for the API-key check, base URL, version header,
 * timeout, default model and tool-use plumbing — shared by AI listing copy
 * and the bilingual shop concierge (M2.2). When no key is configured the
 * client reports itself unconfigured so callers fall back to deterministic
 * local behaviour and the test suite never touches the network (Hard Rule 10).
 */
class ClaudeClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const VERSION = '2023-06-01';

    public function configured(): bool
    {
        return ! empty(config('services.anthropic.key'));
    }

    public function model(): string
    {
        return (string) config('services.anthropic.model', 'claude-haiku-4-5-20251001');
    }

    /**
     * Low-level call. Returns the decoded response (content blocks, stop_reason
     * …) so callers can drive a tool-use loop. Throws on a transport/HTTP error
     * — callers decide whether to fall back.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createMessage(array $payload): array
    {
        $payload += ['model' => $this->model(), 'max_tokens' => 1024];

        return $this->http()->post(self::ENDPOINT, $payload)->throw()->json();
    }

    /**
     * Convenience single-shot completion. Returns the concatenated text of the
     * response, or null when unconfigured / on any failure (never throws).
     *
     * @param  array<string, mixed>  $opts
     */
    public function complete(string $prompt, array $opts = []): ?string
    {
        if (! $this->configured()) {
            return null;
        }

        try {
            return $this->text($this->createMessage([
                'messages' => [['role' => 'user', 'content' => $prompt]],
                ...$opts,
            ]));
        } catch (Throwable $e) {
            Log::warning('Claude completion failed.', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Concatenate the text blocks of a Messages response.
     *
     * @param  array<string, mixed>  $response
     */
    public function text(array $response): string
    {
        return collect($response['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");
    }

    private function http(): PendingRequest
    {
        return Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => self::VERSION,
        ])->timeout((int) config('services.anthropic.timeout', 30));
    }
}
