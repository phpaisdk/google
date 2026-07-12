<?php

declare(strict_types=1);

use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Generate;
use AiSdk\Google;
use AiSdk\Google\Tests\Fakes\FakeHttpClient;
use AiSdk\Support\Sdk;
use Nyholm\Psr7\Factory\Psr17Factory;

afterEach(function () {
    Generate::reset();
    Google::reset();
});

function configureGoogleMediaWith(FakeHttpClient $client): void
{
    $factory = new Psr17Factory;
    Generate::configure(new Sdk(
        httpClient: $client,
        requestFactory: $factory,
        streamFactory: $factory,
    ));

    Google::create(['apiKey' => 'gemini-test']);
}

it('sends image generation to the Gemini interactions endpoint using the documented media schema', function () {
    $client = new FakeHttpClient(200, json_encode([
        'candidates' => [[
        ]],
        'output_image' => [
            'data' => base64_encode('image-bytes'),
            'mime_type' => 'image/png',
        ],
    ]));
    configureGoogleMediaWith($client);

    $result = Generate::image('A tiny banana spaceship')
        ->model(Google::image('gemini-3.1-flash-image'))
        ->aspectRatio('16:9')
        ->size('2048x2048')
        ->run();

    expect($result->output->bytes())->toBe('image-bytes')
        ->and($client->lastRequest?->getUri()->getPath())->toBe('/v1beta/interactions')
        ->and($client->sentBody())->toBe([
            'model' => 'gemini-3.1-flash-image',
            'input' => [['type' => 'text', 'text' => 'A tiny banana spaceship']],
            'response_format' => [
                'type' => 'image',
                'aspect_ratio' => '16:9',
                'image_size' => '2K',
            ],
        ]);
});

it('fails image generation when Gemini returns no output image data', function () {
    $client = new FakeHttpClient(200, json_encode([
        'output_text' => 'No image',
    ]));
    configureGoogleMediaWith($client);

    expect(fn () => Generate::image('A tiny banana spaceship')
        ->model(Google::image('gemini-3.1-flash-image'))
        ->run())
        ->toThrow(InvalidResponseException::class, 'Google returned no generated image.');
});

it('fails image generation when Gemini returns malformed output image data', function () {
    $client = new FakeHttpClient(200, json_encode([
        'output_image' => ['data' => 'not-base64!', 'mime_type' => 'image/png'],
    ]));
    configureGoogleMediaWith($client);

    expect(fn () => Generate::image('A tiny banana spaceship')
        ->model(Google::image('gemini-3.1-flash-image'))
        ->run())
        ->toThrow(InvalidResponseException::class, 'Google returned no generated image.');
});

it('sends speech generation to the Gemini interactions endpoint and parses output audio', function () {
    $client = new FakeHttpClient(200, json_encode([
        'output_audio' => [
            'data' => base64_encode('audio-bytes'),
            'mime_type' => 'audio/L16;codec=pcm;rate=24000',
        ],
    ]));
    configureGoogleMediaWith($client);

    $result = Generate::speech('Welcome')
        ->model(Google::speech('gemini-3.1-flash-tts-preview'))
        ->voice('Kore')
        ->run();

    expect($result->output->data)->toBe('audio-bytes')
        ->and($result->output->mimeType)->toBe('audio/L16;codec=pcm;rate=24000')
        ->and($client->lastRequest?->getUri()->getPath())->toBe('/v1beta/interactions')
        ->and($client->sentBody())->toBe([
            'model' => 'gemini-3.1-flash-tts-preview',
            'input' => 'Welcome',
            'response_format' => ['type' => 'audio'],
            'generation_config' => [
                'speech_config' => [
                    ['voice' => 'Kore'],
                ],
            ],
        ]);
});

it('fails speech generation when Gemini returns malformed output audio', function () {
    $client = new FakeHttpClient(200, json_encode([
        'output_audio' => ['data' => 'not-base64', 'mime_type' => 'audio/pcm'],
    ]));
    configureGoogleMediaWith($client);

    expect(fn () => Generate::speech('Welcome')
        ->model(Google::speech('gemini-3.1-flash-tts-preview'))
        ->run())
        ->toThrow(InvalidResponseException::class, 'Google returned no generated audio.');
});
