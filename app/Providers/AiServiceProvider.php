<?php

namespace App\Providers;

use App\Services\Ai\GeminiCredentialResolver;
use App\Services\Ai\OpenAiClient;
use App\Services\Ai\VertexAccessTokenProvider;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OpenAiClient::class, function (): OpenAiClient {
            $provider = (string) config('ai.provider', 'openai');

            if ($provider === 'gemini') {
                return $this->geminiClient();
            }

            return new OpenAiClient(
                apiKey: config('ai.openai.key'),
                baseUrl: rtrim((string) config('ai.openai.base_url'), '/'),
                model: (string) config('ai.openai.model'),
                timeout: (int) config('ai.openai.timeout'),
                maxOutputTokens: (int) config('ai.openai.max_output_tokens'),
                organization: config('ai.openai.organization'),
                transport: 'responses',
                authMethod: 'openai_key',
            );
        });
    }

    private function geminiClient(): OpenAiClient
    {
        $resolver = app(GeminiCredentialResolver::class);
        $credentialsPath = $resolver->resolvePath();
        $vertexBaseUrl = $resolver->vertexOpenAiBaseUrl();

        if ($credentialsPath !== null && $vertexBaseUrl !== null) {
            $tokenProvider = new VertexAccessTokenProvider($credentialsPath);
            $model = $this->vertexModelName((string) config('ai.gemini.model'));

            return new OpenAiClient(
                apiKey: null,
                baseUrl: $vertexBaseUrl,
                model: $model,
                timeout: (int) config('ai.openai.timeout'),
                maxOutputTokens: (int) config('ai.openai.max_output_tokens'),
                organization: null,
                transport: 'chat',
                accessTokenResolver: fn (): string => $tokenProvider->get(),
                authMethod: 'vertex_service_account',
            );
        }

        return new OpenAiClient(
            apiKey: config('ai.gemini.key'),
            baseUrl: rtrim((string) config('ai.gemini.base_url'), '/'),
            model: (string) config('ai.gemini.model'),
            timeout: (int) config('ai.openai.timeout'),
            maxOutputTokens: (int) config('ai.openai.max_output_tokens'),
            organization: null,
            transport: 'chat',
            authMethod: 'gemini_api_key',
        );
    }

    /** Vertex OpenAI-compatible endpoints require publisher/model (e.g. google/gemini-2.5-flash). */
    private function vertexModelName(string $model): string
    {
        return str_contains($model, '/') ? $model : "google/{$model}";
    }
}
