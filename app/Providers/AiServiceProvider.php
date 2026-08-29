<?php

namespace App\Providers;

use App\Services\Ai\OpenAiClient;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OpenAiClient::class, fn (): OpenAiClient => new OpenAiClient(
            apiKey: config('ai.openai.key'),
            baseUrl: rtrim((string) config('ai.openai.base_url'), '/'),
            model: (string) config('ai.openai.model'),
            timeout: (int) config('ai.openai.timeout'),
            maxOutputTokens: (int) config('ai.openai.max_output_tokens'),
            organization: config('ai.openai.organization'),
        ));
    }
}
