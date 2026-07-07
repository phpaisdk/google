<?php

declare(strict_types=1);

namespace AiSdk\Google\Models;

use AiSdk\Capability;
use AiSdk\CapabilitySupport;
use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\TextModelInterface;
use AiSdk\Google\GoogleOptions;
use AiSdk\Google\Support\GoogleRequestBuilder;
use AiSdk\Google\Support\GoogleResponseParser;
use AiSdk\Google\Support\GoogleStreamParser;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Support\ModelCatalog;
use AiSdk\Support\ModelRegistry;
use AiSdk\Utils\Support\Url;
use Generator;

final class GoogleTextModel extends BaseModel implements TextModelInterface
{
    public function __construct(
        private readonly string $modelId,
        private readonly GoogleOptions $options,
        private readonly ?ModelRegistry $registry = null,
    ) {}

    public function provider(): string
    {
        return GoogleOptions::PROVIDER_NAME;
    }

    public function modelId(): string
    {
        return $this->modelId;
    }

    /**
     * @return array<int, Capability>
     */
    public function capabilities(): array
    {
        $definition = $this->registry?->resolve($this->provider(), $this->modelId);
        if ($definition !== null) {
            return $this->configuredCapabilities($definition->capabilities);
        }

        return $this->configuredCapabilities($this->catalog()->capabilities($this->modelId));
    }

    public function capability(Capability $capability): CapabilitySupport
    {
        $configured = $this->configuredCapability($capability);
        if ($configured !== null) {
            return $configured;
        }

        $registered = $this->registry?->capability($this->provider(), $this->modelId, $capability);
        if ($registered !== null) {
            return $registered;
        }

        $support = $this->catalog()->capability($this->modelId, $capability);
        if (! $support->isSupported()
            && $capability === Capability::TextGeneration
            && $this->catalog()->capabilities($this->modelId) === []) {
            return CapabilitySupport::supported($capability, 'unknown-model-fallback');
        }

        return $support;
    }

    public function generate(TextModelRequest $request): TextModelResponse
    {
        $body = GoogleRequestBuilder::build($this->modelId, $this->provider(), $request, stream: false);
        $url = Url::joinPath($this->options->baseUrl, '/interactions');

        $payload = $this->runner($this->options->sdk)
            ->postJson($url, $body, $this->options->authHeaders(), $this->provider());

        return GoogleResponseParser::parse($payload);
    }

    public function stream(TextModelRequest $request): Generator
    {
        $body = GoogleRequestBuilder::build($this->modelId, $this->provider(), $request, stream: true);
        $url = Url::joinPath($this->options->baseUrl, '/interactions?alt=sse');

        $events = $this->runner($this->options->sdk)
            ->postStream($url, $body, $this->options->authHeaders(), $this->provider());

        yield from GoogleStreamParser::parse($events);
    }

    private function catalog(): ModelCatalog
    {
        return ModelCatalog::fromFile(dirname(__DIR__, 2).'/resources/models.json');
    }
}
