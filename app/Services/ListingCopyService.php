<?php

namespace App\Services;

use App\Services\Ai\ClaudeClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AI listing-copy generation (docs/ROADMAP.md M1.6). Uses Claude when an API
 * key is configured, otherwise a deterministic multilingual template so the
 * feature still works locally / in tests. Output is a DRAFT for seller review —
 * `en` is always populated (Hard Rule 7). Shares the one Claude transport
 * (M2.0 ClaudeClient) so timeout/version/mocking stay consistent.
 */
class ListingCopyService
{
    public function __construct(private ClaudeClient $claude) {}

    public function configured(): bool
    {
        return $this->claude->configured();
    }

    /**
     * @param  array<int, string>  $attributes  human attribute values for context
     * @return array{en: string, ms: string, vi: string}
     */
    public function generate(string $title, array $attributes = []): array
    {
        $title = trim($title);

        if ($title === '') {
            return ['en' => '', 'ms' => '', 'vi' => ''];
        }

        if ($this->configured()) {
            try {
                return $this->viaClaude($title, $attributes);
            } catch (Throwable $e) {
                Log::warning('AI listing copy failed; using template.', ['error' => $e->getMessage()]);
            }
        }

        return $this->template($title, $attributes);
    }

    /** @return array{en: string, ms: string, vi: string} */
    private function viaClaude(string $title, array $attributes): array
    {
        $attrLine = $attributes !== [] ? ' Key attributes: '.implode(', ', $attributes).'.' : '';

        $prompt = 'Write a concise, honest e-commerce product description for a Malaysian marketplace listing. '
            ."Product: \"{$title}\".{$attrLine} "
            .'Return STRICT JSON only, no markdown: {"en":"<2-3 sentence English description>","ms":"<natural Malay translation>","vi":"<natural Vietnamese translation>"}.';

        $response = $this->claude->createMessage([
            'max_tokens' => 600,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $json = json_decode($this->claude->text($response), true);

        if (! is_array($json) || ! isset($json['en'])) {
            return $this->template($title, $attributes);
        }

        return [
            'en' => (string) $json['en'],
            'ms' => (string) ($json['ms'] ?? ''),
            'vi' => (string) ($json['vi'] ?? ''),
        ];
    }

    /** @return array{en: string, ms: string, vi: string} */
    private function template(string $title, array $attributes): array
    {
        $features = $attributes !== [] ? ' Features: '.implode(', ', $attributes).'.' : '';

        return [
            'en' => "{$title} — quality you can trust.{$features} Carefully selected for Malaysian shoppers, with fast shipping and buyer protection.",
            'ms' => "{$title} — kualiti yang anda boleh percayai. Dipilih khas untuk pembeli di Malaysia, dengan penghantaran pantas dan perlindungan pembeli.",
            'vi' => "{$title} — chất lượng đáng tin cậy. Được tuyển chọn kỹ lưỡng cho người mua sắm tại Malaysia, với dịch vụ giao hàng nhanh và chính sách bảo vệ người mua.",
        ];
    }
}
