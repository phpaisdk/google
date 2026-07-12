<?php

declare(strict_types=1);
use AiSdk\Google\GoogleOptions;
use AiSdk\Google\Models\GoogleVideoModel;
use AiSdk\Google\Tests\Fakes\FakeHttpClient;
use AiSdk\Requests\VideoOutputOptions;
use AiSdk\Requests\VideoRequest;
use AiSdk\Responses\VideoJob;
use AiSdk\Responses\VideoJobStatus;
use AiSdk\Support\Sdk;
use Nyholm\Psr7\Factory\Psr17Factory;

function googleVideoOptions(FakeHttpClient $client): GoogleOptions
{
    $f = new Psr17Factory;

    return new GoogleOptions('key', 'https://generativelanguage.googleapis.com/v1beta', sdk: new Sdk($client, $f, $f));
}
it('starts Google Veo operations', function () {
    $c = new FakeHttpClient(200, json_encode(['name' => 'operations/video-1']));
    $m = new GoogleVideoModel('veo-3.1-generate-preview', googleVideoOptions($c));
    $j = $m->generate(new VideoRequest('Ocean', output: new VideoOutputOptions('16:9', '1920x1080', 8)));
    expect($j->id)->toBe('operations/video-1')->and($c->lastRequest?->getUri()->getPath())->toBe('/v1beta/models/veo-3.1-generate-preview:predictLongRunning')->and($c->sentBody()['parameters'])->toMatchArray(['aspectRatio' => '16:9', 'resolution' => '1080p', 'durationSeconds' => 8.0]);
});
it('polls completed Google Veo operations', function () {
    $c = new FakeHttpClient(200, json_encode(['done' => true, 'response' => ['generateVideoResponse' => ['generatedSamples' => [['video' => ['uri' => 'https://google/video.mp4']]]]]]));
    $m = new GoogleVideoModel('veo', googleVideoOptions($c));
    $j = $m->poll(new VideoJob('operations/video-1', 'google', 'veo'));
    expect($j->status)->toBe(VideoJobStatus::Succeeded)->and($j->result?->url)->toBe('https://google/video.mp4?key=key');
});
