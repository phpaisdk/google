<?php

declare(strict_types=1);

namespace AiSdk\Google\Models;

use AiSdk\Contracts\BaseModel;
use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Generate;
use AiSdk\Google\GoogleOptions;
use AiSdk\Google\Live\GoogleLiveConfiguration;
use AiSdk\Google\Live\GoogleLiveSessionDriver;
use AiSdk\Live\ClientSecret;
use AiSdk\Live\Contracts\LiveClientSecretModelInterface;
use AiSdk\Live\Contracts\LiveSessionDriverInterface;
use AiSdk\Live\Contracts\TransportInterface;
use AiSdk\Live\LiveRequest;

final class GoogleLiveModel extends BaseModel implements LiveClientSecretModelInterface
{
    public function __construct(
        private readonly string $modelId,
        private readonly GoogleOptions $options,
    ) {}

    public function provider(): string
    {
        return GoogleOptions::PROVIDER_NAME;
    }

    public function modelId(): string
    {
        return $this->modelId;
    }

    public function createLiveSession(LiveRequest $request, TransportInterface $transport): LiveSessionDriverInterface
    {
        return new GoogleLiveSessionDriver($this->modelId, $this->options, $request, $transport);
    }

    public function clientSecret(LiveRequest $request): ClientSecret
    {
        $setup = GoogleLiveConfiguration::setup($this->modelId, $request);
        $generationConfig = $setup['generationConfig'] ?? [];
        $config = is_array($generationConfig) ? $generationConfig : [];

        foreach ($setup as $key => $value) {
            if ($key !== 'model' && $key !== 'generationConfig') {
                $config[$key] = $value;
            }
        }

        /** @var array<string, mixed> $body */
        $body = [
            'uses' => 1,
            'liveConnectConstraints' => [
                'model' => $this->modelId,
                'config' => $config,
            ],
        ];

        $provider = $request->providerOptions[GoogleOptions::PROVIDER_NAME] ?? [];
        $tokenOptions = $provider['clientSecret'] ?? $provider['client_secret'] ?? null;
        if (is_array($tokenOptions)) {
            $body = array_replace_recursive($body, $tokenOptions);
        }

        $sdk = $this->options->sdk ?? Generate::sdk();
        $response = $this->runner($sdk)->postJson(
            $this->options->liveAuthTokenUrl(),
            $body,
            $this->options->authHeaders(),
            $this->provider(),
        );

        $value = $response['name'] ?? null;
        if (! is_string($value) || $value === '') {
            throw InvalidResponseException::forProvider(
                $this->provider(),
                'Google returned no Live ephemeral token.',
                ['response' => $response],
            );
        }

        $expiresAt = null;
        if (is_string($response['expireTime'] ?? null)) {
            $timestamp = strtotime($response['expireTime']);
            $expiresAt = $timestamp === false ? null : $timestamp;
        }

        return new ClientSecret($value, $expiresAt, raw: $response);
    }
}
