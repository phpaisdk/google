<?php

declare(strict_types=1);

namespace AiSdk\Google\Models;

use AiSdk\ContentSource;
use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\VideoModelInterface;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Google\GoogleOptions;
use AiSdk\Requests\VideoRequest;
use AiSdk\Responses\VideoJob;
use AiSdk\Responses\VideoJobStatus;
use AiSdk\Results\VideoData;
use AiSdk\Support\Usage;
use AiSdk\Utils\Support\Url;

final class GoogleVideoModel extends BaseModel implements VideoModelInterface
{
    public function __construct(private readonly string $modelId, private readonly GoogleOptions $options) {}

    public function provider(): string
    {
        return GoogleOptions::PROVIDER_NAME;
    }

    public function modelId(): string
    {
        return $this->modelId;
    }

    public function generate(VideoRequest $r): VideoJob
    {
        if ($r->video !== null) {
            throw new InvalidArgumentException('Google Veo does not accept a source video for this generation API.');
        }
        $o = $r->providerOptionsFor($this->provider());
        $instance = ['prompt' => $r->prompt];
        if ($r->image) {
            if ($r->image->source() === ContentSource::Url) {
                throw new InvalidArgumentException('Google video generation requires inline image data; remote image URLs are not supported.');
            }$instance['image'] = ['inlineData' => ['mimeType' => $r->image->mimeType() ?? 'image/png', 'data' => $r->image->base64Data()]];
        }if (is_array($o['referenceImages'] ?? null)) {
            $instance['referenceImages'] = $o['referenceImages'];
        }$params = array_filter(['aspectRatio' => $r->output?->aspectRatio, 'resolution' => $this->resolution($r->output?->resolution), 'durationSeconds' => $r->output?->duration, 'seed' => $r->output?->seed], fn ($v) => $v !== null);
        $params = array_replace($params, array_diff_key($o, array_flip(['pollIntervalMs', 'pollTimeoutMs', 'referenceImages'])));
        $p = $this->runner($this->options->sdk)->postJson(Url::joinPath($this->options->baseUrl, '/models/'.rawurlencode($this->modelId).':predictLongRunning'), ['instances' => [$instance], 'parameters' => $params], $this->options->authHeaders(), $this->provider());
        $id = $p['name'] ?? null;
        if (! is_string($id) || $id === '') {
            throw InvalidResponseException::forProvider($this->provider(), 'Google returned no video operation name.', ['body' => $p]);
        }

        return new VideoJob($id, $this->provider(), $this->modelId, rawResponse: $p, providerMetadata: [$this->provider() => ['operationName' => $id, 'pollIntervalMs' => (int) ($o['pollIntervalMs'] ?? 10000), 'pollTimeoutMs' => (int) ($o['pollTimeoutMs'] ?? 600000)]]);
    }

    public function poll(VideoJob $job): VideoJob
    {
        $p = $this->runner($this->options->sdk)->getJson(Url::joinPath($this->options->baseUrl, '/'.$job->id), $this->options->authHeaders(), $this->provider());
        if (! ($p['done'] ?? false)) {
            return new VideoJob($job->id, $job->provider, $job->modelId, VideoJobStatus::Running, rawResponse: $p, providerMetadata: $job->providerMetadata);
        }if (isset($p['error'])) {
            return new VideoJob($job->id, $job->provider, $job->modelId, VideoJobStatus::Failed, errorMessage: (string) ($p['error']['message'] ?? 'Google video generation failed.'), rawResponse: $p, providerMetadata: $job->providerMetadata);
        }$uri = $p['response']['generateVideoResponse']['generatedSamples'][0]['video']['uri'] ?? null;
        if (! is_string($uri) || $uri === '') {
            return new VideoJob($job->id, $job->provider, $job->modelId, VideoJobStatus::Failed, errorMessage: 'Google completed without a video URI.', rawResponse: $p, providerMetadata: $job->providerMetadata);
        }$url = $uri.(str_contains($uri, '?') ? '&' : '?').'key='.rawurlencode($this->options->apiKey);

        return new VideoJob($job->id, $job->provider, $job->modelId, VideoJobStatus::Succeeded, new VideoData(url: $url), usage: Usage::empty(), rawResponse: $p, providerMetadata: $job->providerMetadata);
    }

    private function resolution(?string $r): ?string
    {
        return match ($r) {
            '1280x720' => '720p','1920x1080' => '1080p','3840x2160' => '4k',default => $r
        };
    }
}
