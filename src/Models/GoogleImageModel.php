<?php

declare(strict_types=1);

namespace AiSdk\Google\Models;

use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Google\GoogleOptions;
use AiSdk\Google\Support\GoogleImageRequestBuilder;
use AiSdk\Google\Support\GoogleImageResponseParser;
use AiSdk\Requests\ImageRequest;
use AiSdk\Responses\ImageResponse;
use AiSdk\Utils\Support\Url;

final class GoogleImageModel extends BaseModel implements ImageModelInterface
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

    public function generate(ImageRequest $request): ImageResponse
    {
        $body = GoogleImageRequestBuilder::build($this->provider(), $request);
        $url = Url::joinPath($this->options->baseUrl, "/models/{$this->modelId}:generateContent");

        $payload = $this->runner($this->options->sdk)
            ->postJson($url, $body, $this->options->authHeaders(), $this->provider());

        return GoogleImageResponseParser::parse($payload, $this->provider());
    }
}
