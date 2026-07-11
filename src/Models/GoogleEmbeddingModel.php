<?php

declare(strict_types=1);

namespace AiSdk\Google\Models;

use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\EmbeddingModelInterface;
use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Google\GoogleOptions;
use AiSdk\Requests\EmbeddingRequest;
use AiSdk\Responses\EmbeddingResponse;
use AiSdk\Results\EmbeddingData;
use AiSdk\Support\Usage;
use AiSdk\Utils\Support\Url;

final class GoogleEmbeddingModel extends BaseModel implements EmbeddingModelInterface
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

    public function generate(EmbeddingRequest $request): EmbeddingResponse
    {
        $model = $this->modelResourceName();
        $requests = array_map(
            fn (string $input): array => $this->buildRequest($model, $input, $request),
            $request->inputs,
        );

        $batch = count($requests) > 1;
        $body = $batch ? ['requests' => $requests] : $requests[0];
        $raw = $request->providerOptionsFor($this->provider())['raw'] ?? null;
        if (is_array($raw)) {
            $body = array_replace_recursive($body, $raw);
        }

        $method = $batch ? 'batchEmbedContents' : 'embedContent';
        $payload = $this->runner($this->options->sdk)->postJson(
            Url::joinPath($this->options->baseUrl, "/{$model}:{$method}"),
            $body,
            $this->options->authHeaders(),
            $this->provider(),
        );

        $embeddings = $this->embeddings($payload, $batch);
        if (count($embeddings) !== count($request->inputs)) {
            throw InvalidResponseException::forProvider(
                $this->provider(),
                'Google returned an unexpected number of embeddings.',
                ['body' => $payload],
            );
        }

        $usageMetadata = $payload['usageMetadata'] ?? null;
        $inputTokens = is_array($usageMetadata) && is_numeric($usageMetadata['promptTokenCount'] ?? null)
            ? (int) $usageMetadata['promptTokenCount']
            : 0;

        return new EmbeddingResponse(
            embeddings: $embeddings,
            usage: new Usage(inputTokens: $inputTokens),
            rawResponse: $payload,
            providerMetadata: [
                $this->provider() => array_filter([
                    'model' => $this->modelId,
                    'usageMetadata' => is_array($usageMetadata) ? $usageMetadata : null,
                ], static fn (mixed $value): bool => $value !== null),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRequest(string $model, string $input, EmbeddingRequest $request): array
    {
        $body = [
            'model' => $model,
            'content' => [
                'parts' => [
                    ['text' => $input],
                ],
            ],
        ];

        $options = $request->providerOptionsFor($this->provider());
        $providerConfig = $options['embedContentConfig'] ?? null;
        $config = array_filter([
            'outputDimensionality' => $request->dimensions,
        ], static fn (mixed $value): bool => $value !== null);

        if (is_array($providerConfig)) {
            $config = array_replace_recursive($config, $providerConfig);
        }

        if ($config !== []) {
            $body['embedContentConfig'] = $config;
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, EmbeddingData>
     */
    private function embeddings(array $payload, bool $batch): array
    {
        $values = $batch
            ? ($payload['embeddings'] ?? null)
            : (isset($payload['embedding']) ? [$payload['embedding']] : null);

        if (! is_array($values) || $values === []) {
            throw InvalidResponseException::forProvider(
                $this->provider(),
                'Google returned no embeddings.',
                ['body' => $payload],
            );
        }

        $embeddings = [];
        foreach ($values as $index => $embedding) {
            if (! is_array($embedding)) {
                $this->throwInvalidEmbedding($payload);
            }

            $vector = $this->vector($embedding['values'] ?? null);
            if ($vector === []) {
                $this->throwInvalidEmbedding($payload);
            }

            $embeddings[] = new EmbeddingData($vector, (int) $index);
        }

        return $embeddings;
    }

    /**
     * @return array<int, float>
     */
    private function vector(mixed $values): array
    {
        if (! is_array($values) || ! array_is_list($values) || $values === []) {
            return [];
        }

        $vector = [];
        foreach ($values as $value) {
            if (! is_int($value) && ! is_float($value)) {
                return [];
            }

            $vector[] = (float) $value;
        }

        return $vector;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function throwInvalidEmbedding(array $payload): never
    {
        throw InvalidResponseException::forProvider(
            $this->provider(),
            'Google returned an invalid embedding.',
            ['body' => $payload],
        );
    }

    private function modelResourceName(): string
    {
        return str_starts_with($this->modelId, 'models/')
            ? $this->modelId
            : "models/{$this->modelId}";
    }
}
