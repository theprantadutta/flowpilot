<?php

namespace App\Support\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Freeway, an OpenAI-shaped gateway: POST /chat/completions with the project
 * key in X-Api-Key (a Bearer header is treated as a web-panel login and
 * refused). It has no streaming, tools or response_format, so structured
 * output is asked for in the prompt and validated by the caller.
 */
class FreewayProvider implements AiProvider
{
    /**
     * @param  array{url?: string|null, key?: string|null, model?: string|null, reasoning_effort?: string|null, timeout?: int|null}  $config
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'freeway';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['url'] ?? null) && filled($this->config['key'] ?? null);
    }

    public function complete(AiPrompt $prompt): AiCompletion
    {
        if (! $this->isConfigured()) {
            throw AiUnavailable::notConfigured();
        }

        $effort = $this->config['reasoning_effort'] ?? null;
        $started = hrtime(true);

        try {
            $response = Http::withHeaders(['X-Api-Key' => (string) ($this->config['key'] ?? '')])
                ->acceptJson()
                ->timeout(max(30, (int) ($this->config['timeout'] ?? 180)))
                ->connectTimeout(10)
                ->post(rtrim((string) ($this->config['url'] ?? ''), '/').'/chat/completions', array_filter([
                    'model' => (string) ($this->config['model'] ?? 'paid:premium'),
                    'messages' => [
                        ['role' => 'system', 'content' => $prompt->instructions],
                        ['role' => 'user', 'content' => $prompt->input],
                    ],
                    'max_tokens' => max(1, $prompt->maxTokens),
                    'temperature' => 0.2,
                    'reasoning' => in_array($effort, ['low', 'medium', 'high'], true) ? ['effort' => $effort] : null,
                ], fn (mixed $value): bool => $value !== null));
        } catch (ConnectionException $exception) {
            throw str_contains(strtolower($exception->getMessage()), 'timed out') ? AiUnavailable::timedOut() : AiUnavailable::unreachable();
        }

        $duration = (int) ((hrtime(true) - $started) / 1_000_000);

        if (! $response->successful()) {
            $this->logFailure($response);

            throw match ($response->status()) {
                429 => AiUnavailable::rateLimited(),
                502, 503, 504 => AiUnavailable::unreachable(),
                default => AiUnavailable::rejected($response->status()),
            };
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw AiUnavailable::emptyAnswer();
        }

        $model = $response->json('model');
        $promptTokens = $response->json('usage.prompt_tokens');
        $completionTokens = $response->json('usage.completion_tokens');

        return new AiCompletion(
            content: $content,
            model: is_string($model) ? $model : null,
            promptTokens: is_int($promptTokens) ? $promptTokens : null,
            completionTokens: is_int($completionTokens) ? $completionTokens : null,
            durationMs: $duration,
        );
    }

    /**
     * Record why Freeway refused, without the prompt or the key.
     */
    private function logFailure(Response $response): void
    {
        $detail = $response->json('detail');

        Log::warning('Freeway refused a request', [
            'status' => $response->status(),
            'detail' => is_string($detail) ? mb_substr($detail, 0, 300) : null,
        ]);
    }
}
