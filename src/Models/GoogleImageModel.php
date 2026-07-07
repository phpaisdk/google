<?php

declare(strict_types=1);

namespace AiSdk\Google\Models;

use AiSdk\Capability;
use AiSdk\CapabilitySupport;
use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Google\GoogleOptions;
use AiSdk\Google\Support\GoogleImageRequestBuilder;
use AiSdk\Google\Support\GoogleImageResponseParser;
use AiSdk\Requests\ImageRequest;
use AiSdk\Responses\ImageResponse;
use AiSdk\Support\ModelCatalog;
use AiSdk\Support\ModelRegistry;
use AiSdk\Utils\Support\Url;

final class GoogleImageModel extends BaseModel implements ImageModelInterface
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

        return $this->catalog()->capability($this->modelId, $capability);
    }

    public function generate(ImageRequest $request): ImageResponse
    {
        $body = GoogleImageRequestBuilder::build($this->provider(), $request);
        $url = Url::joinPath($this->options->baseUrl, "/models/{$this->modelId}:generateContent");

        $payload = $this->runner($this->options->sdk)
            ->postJson($url, $body, $this->options->authHeaders(), $this->provider());

        return GoogleImageResponseParser::parse($payload, $this->provider());
    }

    private function catalog(): ModelCatalog
    {
        return ModelCatalog::fromFile(dirname(__DIR__, 2).'/resources/models.json');
    }
}
