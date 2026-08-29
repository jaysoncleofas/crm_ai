<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper over the OpenAI Responses API.
 *
 * Knows nothing about the CRM — it takes an input array and tool definitions
 * and hands back the raw response. Errors are translated into a message that is
 * safe to show a user; the provider's own text stays in the log.
 */
class OpenAiClient
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeout,
        private readonly int $maxOutputTokens,
        private readonly ?string $organization = null,
    ) {}

    public function configured(): bool
    {
        return filled($this->apiKey);
    }

    public function model(): string
    {
        return $this->model;
    }

    /**
     * @param  array<int, array<string, mixed>>  $input
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    public function respond(array $input, array $tools, string $instructions): array
    {
        if (! $this->configured()) {
            throw new AssistantException('The assistant is not configured.', 503);
        }

        $payload = [
            'model' => $this->model,
            'instructions' => $instructions,
            'input' => $input,
            'max_output_tokens' => $this->maxOutputTokens,
            // Transcripts live in our own database; there is no reason to ask
            // the provider to retain CRM data as well.
            'store' => false,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->withHeaders(array_filter(['OpenAI-Organization' => $this->organization]))
                ->timeout($this->timeout)
                ->retry(2, 500, throw: false)
                ->acceptJson()
                ->asJson()
                ->post("{$this->baseUrl}/responses", $payload);
        } catch (ConnectionException $e) {
            Log::warning('Assistant: could not reach OpenAI', ['error' => $e->getMessage()]);

            throw new AssistantException('The assistant is unreachable right now. Please try again.', 503);
        }

        if ($response->failed()) {
            // The provider's message can echo request content; keep it server-side.
            Log::warning('Assistant: OpenAI returned an error', [
                'status' => $response->status(),
                'body' => $response->json('error.message') ?? $response->body(),
            ]);

            throw new AssistantException(match (true) {
                $response->status() === 401 => 'The assistant credentials were rejected.',
                $response->status() === 429 => 'The assistant is rate limited. Please try again shortly.',
                default => 'The assistant could not complete that request.',
            }, $response->status() === 429 ? 429 : 502);
        }

        return $response->json() ?? [];
    }

    /** Concatenate the assistant's text output across message items. */
    public function textFrom(array $response): string
    {
        if (is_string($response['output_text'] ?? null) && $response['output_text'] !== '') {
            return $response['output_text'];
        }

        $chunks = [];

        foreach ($response['output'] ?? [] as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ($item['content'] ?? [] as $part) {
                if (($part['type'] ?? null) === 'output_text' && isset($part['text'])) {
                    $chunks[] = $part['text'];
                }
            }
        }

        return trim(implode("\n", $chunks));
    }

    /** @return array<int, array<string, mixed>> */
    public function toolCallsFrom(array $response): array
    {
        return array_values(array_filter(
            $response['output'] ?? [],
            fn (array $item): bool => ($item['type'] ?? null) === 'function_call',
        ));
    }

    public function usageFrom(array $response): array
    {
        $usage = $response['usage'] ?? [];

        return [
            'input_tokens' => $usage['input_tokens'] ?? null,
            'output_tokens' => $usage['output_tokens'] ?? null,
            'total_tokens' => $usage['total_tokens'] ?? null,
        ];
    }
}
