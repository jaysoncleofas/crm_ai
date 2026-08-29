<?php

namespace App\Services\Ai;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/** OAuth access tokens for Vertex AI from a service-account JSON file. */
class VertexAccessTokenProvider
{
    private const SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    public function __construct(private readonly string $credentialsPath) {}

    public function get(): string
    {
        $cacheKey = 'vertex_access_token:'.sha1($this->credentialsPath);

        return Cache::remember($cacheKey, now()->addMinutes(55), function (): string {
            $credentials = new ServiceAccountCredentials(self::SCOPE, $this->credentialsPath);
            $token = $credentials->fetchAuthToken();

            if (! is_array($token) || ! isset($token['access_token'])) {
                Log::warning('Assistant: could not fetch Vertex access token');

                throw new AssistantException('The assistant credentials could not be used.', 503);
            }

            return (string) $token['access_token'];
        });
    }
}
