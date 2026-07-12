<?php

declare(strict_types=1);

namespace AiSdk\Google\Models;

use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\SpeechModelInterface;
use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Google\GoogleOptions;
use AiSdk\Requests\SpeechRequest;
use AiSdk\Responses\SpeechResponse;
use AiSdk\Results\AudioData;
use AiSdk\Support\Usage;
use AiSdk\Utils\Support\Url;

final class GoogleSpeechModel extends BaseModel implements SpeechModelInterface
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

    public function generate(SpeechRequest $request): SpeechResponse
    {
        $body = $this->buildBody($request);
        $url = Url::joinPath($this->options->baseUrl, '/interactions');

        $payload = $this->runner($this->options->sdk)
            ->postJson($url, $body, $this->options->authHeaders(), $this->provider());

        $audio = $this->audio($payload);
        if ($audio === null) {
            throw InvalidResponseException::forProvider($this->provider(), 'Google returned no generated audio.', ['body' => $payload]);
        }

        return new SpeechResponse(
            audio: new AudioData(
                data: $audio['data'],
                mimeType: $audio['mimeType'],
            ),
            usage: Usage::empty(),
            rawResponse: $payload,
            providerMetadata: [$this->provider() => $this->metadata($payload)],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBody(SpeechRequest $request): array
    {
        $body = [
            'model' => $this->modelId,
            'input' => $request->input,
            'response_format' => ['type' => 'audio'],
            'generation_config' => [
                'speech_config' => [
                    [
                        'voice' => $request->voice ?? 'Kore',
                    ],
                ],
            ],
        ];

        $options = $request->providerOptionsFor($this->provider());
        $raw = $options['raw'] ?? null;
        unset($options['raw']);
        $body = array_replace_recursive($body, $options);

        return is_array($raw) ? array_replace_recursive($body, $raw) : $body;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{data: string, mimeType: string}|null
     */
    private function audio(array $payload): ?array
    {
        $outputAudio = $payload['output_audio'] ?? $payload['outputAudio'] ?? null;
        if (! is_array($outputAudio) || ! is_string($outputAudio['data'] ?? null)) {
            return null;
        }

        $data = base64_decode($outputAudio['data'], strict: true);
        if ($data === false || $data === '') {
            return null;
        }

        return [
            'data' => $data,
            'mimeType' => (string) ($outputAudio['mime_type'] ?? $outputAudio['mimeType'] ?? 'audio/pcm'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function metadata(array $payload): array
    {
        $metadata = [];

        foreach (['id', 'model', 'finish_reason', 'finishReason'] as $key) {
            if (array_key_exists($key, $payload)) {
                $metadata[$key] = $payload[$key];
            }
        }

        return $metadata;
    }
}
