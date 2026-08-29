<?php

namespace App\Services\Ai;

/**
 * Locates the Gemini service-account JSON: explicit env path first, then
 * gemini_credentials.json in the project root, then common fallbacks.
 */
class GeminiCredentialResolver
{
    public function resolvePath(): ?string
    {
        foreach ($this->candidatePaths() as $path) {
            $resolved = $this->resolveCandidate($path);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    public function projectId(?string $path = null): ?string
    {
        $path ??= $this->resolvePath();

        if ($path === null) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) ? ($json['project_id'] ?? null) : null;
    }

    public function vertexOpenAiBaseUrl(?string $path = null): ?string
    {
        $path ??= $this->resolvePath();
        $projectId = $this->projectId($path);

        if ($path === null || $projectId === null) {
            return null;
        }

        $location = (string) config('ai.gemini.vertex_location', 'asia-southeast1');

        return "https://{$location}-aiplatform.googleapis.com/v1/projects/{$projectId}/locations/{$location}/endpoints/openapi";
    }

    /** @return array<int, string> */
    private function candidatePaths(): array
    {
        $home = getenv('HOME') ?: '';

        $paths = array_filter([
            config('ai.gemini.service_account_credentials'),
            config('ai.gemini.credentials_path'),
            base_path('gemini_credentials.json'),
            $home !== '' ? "{$home}/Desktop/gemma_ai/backend/gemma_ai_credentials.json" : null,
        ]);

        return array_values($paths);
    }

    private function resolveCandidate(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        $path = $this->expandHome($path);

        if (is_file($path)) {
            return realpath($path) ?: null;
        }

        $relative = base_path($path);

        if (is_file($relative)) {
            return realpath($relative) ?: null;
        }

        return null;
    }

    private function expandHome(string $path): string
    {
        if (! str_starts_with($path, '~/')) {
            return $path;
        }

        $home = getenv('HOME') ?: '';

        return $home !== '' ? $home.substr($path, 1) : $path;
    }
}
