<?php

declare(strict_types=1);

namespace AiSdk\Google\Models;

use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\SpeechModelInterface;
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

        return array_replace_recursive($body, $request->providerOptionsFor($this->provider()));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{data: string, mimeType: string}
     */
    private function audio(array $payload): array
    {
        $outputAudio = $payload['output_audio'] ?? $payload['outputAudio'] ?? null;
        if (is_array($outputAudio)) {
            $data = isset($outputAudio['data']) ? (string) $outputAudio['data'] : '';
            $decoded = base64_decode($data, strict: true);

            return [
                'data' => $decoded === false ? $data : $decoded,
                'mimeType' => isset($outputAudio['mime_type'])
                    ? (string) $outputAudio['mime_type']
                    : (isset($outputAudio['mimeType']) ? (string) $outputAudio['mimeType'] : 'audio/wav'),
            ];
        }

        return ['data' => '', 'mimeType' => 'audio/wav'];
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
