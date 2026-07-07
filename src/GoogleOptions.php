<?php

declare(strict_types=1);

namespace AiSdk\Google;

use AiSdk\Support\Sdk;
use AiSdk\Utils\Support\Env;
use AiSdk\Utils\Support\Url;

final class GoogleOptions
{
    public const string DEFAULT_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    public const string PROVIDER_NAME = 'google';

    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly string $apiKey,
        public readonly string $baseUrl = self::DEFAULT_BASE_URL,
        public readonly array $headers = [],
        public readonly ?Sdk $sdk = null,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config = []): self
    {
        $explicitApiKey = isset($config['apiKey']) ? (string) $config['apiKey'] : null;
        if ($explicitApiKey === null || $explicitApiKey === '') {
            $fallbackApiKey = $_ENV['GEMINI_API_KEY'] ?? $_SERVER['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');
            $explicitApiKey = is_string($fallbackApiKey) && $fallbackApiKey !== '' ? $fallbackApiKey : null;
        }

        $apiKey = Env::loadApiKey(
            $explicitApiKey,
            'GOOGLE_GENERATIVE_AI_API_KEY',
            self::PROVIDER_NAME,
        );

        $baseUrl = Url::withoutTrailingSlash(
            Env::loadOptionalSetting(isset($config['baseUrl']) ? (string) $config['baseUrl'] : null, 'GOOGLE_GENERATIVE_AI_BASE_URL')
                ?? self::DEFAULT_BASE_URL,
        );

        /** @var array<string, string> $headers */
        $headers = isset($config['headers']) && is_array($config['headers']) ? $config['headers'] : [];
        $sdk = $config['sdk'] ?? null;

        return new self(
            apiKey: $apiKey,
            baseUrl: $baseUrl,
            headers: $headers,
            sdk: $sdk instanceof Sdk ? $sdk : null,
        );
    }

    /**
     * @return array<string, string>
     */
    public function authHeaders(): array
    {
        return array_merge(['x-goog-api-key' => $this->apiKey], $this->headers);
    }
}
