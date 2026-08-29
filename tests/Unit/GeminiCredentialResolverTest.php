<?php

use App\Services\Ai\GeminiCredentialResolver;

it('resolves an explicit credentials path', function (): void {
    $path = sys_get_temp_dir().'/crm-gemini-test-'.uniqid().'.json';
    file_put_contents($path, json_encode(['project_id' => 'test-project']));

    config()->set('ai.gemini.service_account_credentials', null);
    config()->set('ai.gemini.credentials_path', $path);

    $resolver = new GeminiCredentialResolver();

    expect($resolver->resolvePath())->toBe(realpath($path))
        ->and($resolver->projectId())->toBe('test-project')
        ->and($resolver->vertexOpenAiBaseUrl())->toBe(
            'https://asia-southeast1-aiplatform.googleapis.com/v1/projects/test-project/locations/asia-southeast1/endpoints/openapi'
        );

    unlink($path);
});

it('falls back through candidate paths when the first is missing', function (): void {
    $path = sys_get_temp_dir().'/crm-gemini-fallback-'.uniqid().'.json';
    file_put_contents($path, json_encode(['project_id' => 'fallback-project']));

    config()->set('ai.gemini.service_account_credentials', '/missing/file.json');
    config()->set('ai.gemini.credentials_path', $path);

    $resolver = new GeminiCredentialResolver();

    expect($resolver->resolvePath())->toBe(realpath($path));

    unlink($path);
});
