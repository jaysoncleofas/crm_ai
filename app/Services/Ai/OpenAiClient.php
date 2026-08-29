<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper over an OpenAI-shaped API.
 *
 * Supports OpenAI's Responses API (default) and chat/completions (Gemini and
 * other OpenAI-compatible hosts). Knows nothing about the CRM — it takes an
 * input array and tool definitions and hands back a normalised response.
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
        private readonly string $transport = 'responses',
        private readonly ?\Closure $accessTokenResolver = null,
        private readonly string $authMethod = 'openai_key',
    ) {}

    public function configured(): bool
    {
        return filled($this->apiKey) || $this->accessTokenResolver !== null;
    }

    public function authMethod(): string
    {
        return $this->authMethod;
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

        if ($this->transport === 'chat') {
            return $this->respondViaChat($input, $tools, $instructions);
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
            $response = $this->request()->post("{$this->baseUrl}/responses", $payload);
        } catch (ConnectionException $e) {
            Log::warning('Assistant: could not reach the model provider', ['error' => $e->getMessage()]);

            throw new AssistantException('The assistant is unreachable right now. Please try again.', 503);
        }

        return $this->handleResponse($response);
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

    /**
     * @param  array<int, array<string, mixed>>  $input
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    private function respondViaChat(array $input, array $tools, string $instructions): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => $this->inputToChatMessages($input, $instructions),
            'max_tokens' => $this->maxOutputTokens,
        ];

        if ($tools !== []) {
            $payload['tools'] = $this->toChatTools($tools);
            $payload['tool_choice'] = 'auto';
        }

        try {
            $response = $this->request()->post("{$this->baseUrl}/chat/completions", $payload);
        } catch (ConnectionException $e) {
            Log::warning('Assistant: could not reach the model provider', ['error' => $e->getMessage()]);

            throw new AssistantException('The assistant is unreachable right now. Please try again.', 503);
        }

        return $this->handleResponse($response, $this->normalizeChatResponse($response->json() ?? []));
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $token = $this->apiKey;

        if ($this->accessTokenResolver !== null) {
            $token = ($this->accessTokenResolver)();
        }

        return Http::withToken((string) $token)
            ->withHeaders(array_filter(['OpenAI-Organization' => $this->organization]))
            ->timeout($this->timeout)
            ->retry(2, 500, throw: false)
            ->acceptJson()
            ->asJson();
    }

    /** @return array<string, mixed> */
    private function handleResponse(Response $response, ?array $body = null): array
    {
        if ($response->failed()) {
            Log::warning('Assistant: model provider returned an error', [
                'status' => $response->status(),
                'body' => $response->json('error.message') ?? $response->body(),
            ]);

            throw new AssistantException(match (true) {
                $response->status() === 401 => 'The assistant credentials were rejected.',
                $response->status() === 429 => 'The assistant is rate limited. Please try again shortly.',
                default => 'The assistant could not complete that request.',
            }, $response->status() === 429 ? 429 : 502);
        }

        return $body ?? ($response->json() ?? []);
    }

    /**
     * @param  array<int, array<string, mixed>>  $input
     * @return array<int, array<string, mixed>>
     */
    private function inputToChatMessages(array $input, string $instructions): array
    {
        $messages = [['role' => 'system', 'content' => $instructions]];
        $pendingToolCalls = [];

        foreach ($input as $item) {
            if (isset($item['role'])) {
                $messages[] = ['role' => $item['role'], 'content' => $item['content']];

                continue;
            }

            if (($item['type'] ?? null) === 'function_call') {
                $pendingToolCalls[] = [
                    'id' => (string) ($item['call_id'] ?? ''),
                    'type' => 'function',
                    'function' => [
                        'name' => (string) ($item['name'] ?? ''),
                        'arguments' => (string) ($item['arguments'] ?? '{}'),
                    ],
                ];

                continue;
            }

            if (($item['type'] ?? null) === 'function_call_output') {
                if ($pendingToolCalls !== []) {
                    $messages[] = [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => $pendingToolCalls,
                    ];
                    $pendingToolCalls = [];
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) ($item['call_id'] ?? ''),
                    'content' => (string) ($item['output'] ?? ''),
                ];
            }
        }

        if ($pendingToolCalls !== []) {
            $messages[] = [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => $pendingToolCalls,
            ];
        }

        return $messages;
    }

    /**
     * Responses-API tool definitions -> chat/completions tool definitions.
     *
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<int, array<string, mixed>>
     */
    private function toChatTools(array $tools): array
    {
        return array_map(fn (array $tool): array => [
            'type' => 'function',
            'function' => [
                'name' => $tool['name'] ?? '',
                'description' => $tool['description'] ?? '',
                'parameters' => $tool['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
            ],
        ], $tools);
    }

    /** @return array<string, mixed> */
    private function normalizeChatResponse(array $json): array
    {
        $message = $json['choices'][0]['message'] ?? [];
        $output = [];

        if (! empty($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $toolCall) {
                $output[] = [
                    'type' => 'function_call',
                    'call_id' => $toolCall['id'] ?? '',
                    'name' => $toolCall['function']['name'] ?? '',
                    'arguments' => $toolCall['function']['arguments'] ?? '{}',
                ];
            }
        } else {
            $output[] = [
                'type' => 'message',
                'role' => 'assistant',
                'content' => [['type' => 'output_text', 'text' => (string) ($message['content'] ?? '')]],
            ];
        }

        return [
            'output' => $output,
            'usage' => [
                'input_tokens' => $json['usage']['prompt_tokens'] ?? null,
                'output_tokens' => $json['usage']['completion_tokens'] ?? null,
                'total_tokens' => $json['usage']['total_tokens'] ?? null,
            ],
        ];
    }
}
