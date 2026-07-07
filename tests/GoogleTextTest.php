<?php

declare(strict_types=1);

use AiSdk\Capability;
use AiSdk\Generate;
use AiSdk\Google;
use AiSdk\Google\Tests\Fakes\FakeHttpClient;
use AiSdk\Reasoning;
use AiSdk\Support\Sdk;
use Nyholm\Psr7\Factory\Psr17Factory;

afterEach(function () {
    Generate::reset();
    Google::reset();
});

function configureGoogleWith(FakeHttpClient $client): void
{
    $factory = new Psr17Factory;
    Generate::configure(new Sdk(
        httpClient: $client,
        requestFactory: $factory,
        streamFactory: $factory,
    ));
}

it('generates text end to end through the Google vertical', function () {
    $client = new FakeHttpClient(200, json_encode([
        'id' => 'interaction_google',
        'model' => 'gemini-3.5-flash',
        'output_text' => 'Hello from Gemini',
        'finish_reason' => 'stop',
        'usage_metadata' => ['input_tokens' => 6, 'output_tokens' => 3, 'total_tokens' => 9],
    ]));
    configureGoogleWith($client);

    Google::create(['apiKey' => 'gemini-test']);

    $result = Generate::text('Hi')->model(Google::model('gemini-3.5-flash'))->run();

    expect($result->text)->toBe('Hello from Gemini')
        ->and($result->usage->inputTokens)->toBe(6)
        ->and($result->providerMetadata['google']['id'])->toBe('interaction_google')
        ->and($result->providerMetadata['google']['model'])->toBe('gemini-3.5-flash');

    $body = $client->sentBody();
    expect($body['model'])->toBe('gemini-3.5-flash')
        ->and($body['input'])->toBe('Hi')
        ->and($body['generation_config']['max_output_tokens'])->toBe(1024);

    expect($client->lastRequest->getUri()->getPath())->toBe('/v1beta/interactions')
        ->and($client->lastRequest->getHeaderLine('x-goog-api-key'))->toBe('gemini-test');
});

it('normalizes camel case text usage fields', function () {
    $client = new FakeHttpClient(200, json_encode([
        'output_text' => 'Hello from Gemini',
        'finish_reason' => 'stop',
        'usage' => ['inputTokens' => 14, 'outputTokens' => 7, 'totalTokens' => 21],
    ]));
    configureGoogleWith($client);

    Google::create(['apiKey' => 'gemini-test']);

    $result = Generate::text('Hi')->model(Google::model('gemini-3.5-flash'))->run();

    expect($result->usage->inputTokens)->toBe(14)
        ->and($result->usage->outputTokens)->toBe(7)
        ->and($result->usage->totalTokens)->toBe(21);
});

it('generates images through the Google vertical', function () {
    $client = new FakeHttpClient(200, json_encode([
        'model' => 'gemini-3.1-flash-image',
        'candidates' => [[
            'content' => [
                'parts' => [
                    ['text' => 'Generated image'],
                    ['inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode('png-bytes')]],
                ],
            ],
        ]],
        'usageMetadata' => ['promptTokenCount' => 5, 'candidatesTokenCount' => 7, 'totalTokenCount' => 12],
    ]));
    configureGoogleWith($client);

    Google::create(['apiKey' => 'gemini-test']);

    $result = Generate::image()
        ->model(Google::image('gemini-3.1-flash-image'))
        ->prompt('A tiny banana spaceship')
        ->aspectRatio('16:9')
        ->size('2048x2048')
        ->run();

    expect($result->output->base64)->toBe(base64_encode('png-bytes'))
        ->and($result->output->mimeType)->toBe('image/png')
        ->and($result->usage->inputTokens)->toBe(5)
        ->and($result->usage->outputTokens)->toBe(7);

    $body = $client->sentBody();
    expect($body['contents'][0]['parts'][0]['text'])->toBe('A tiny banana spaceship')
        ->and($body['generation_config']['response_modalities'])->toBe(['TEXT', 'IMAGE'])
        ->and($body['generation_config']['image_config']['aspect_ratio'])->toBe('16:9')
        ->and($body['generation_config']['image_config']['image_size'])->toBe('2K');

    expect($client->lastRequest->getUri()->getPath())->toBe('/v1beta/models/gemini-3.1-flash-image:generateContent')
        ->and($client->lastRequest->getHeaderLine('x-goog-api-key'))->toBe('gemini-test');
});

it('sends system instructions and thinking level through generation config', function () {
    $client = new FakeHttpClient(200, json_encode([
        'output_text' => 'Done',
        'finish_reason' => 'stop',
    ]));
    configureGoogleWith($client);
    Google::create(['apiKey' => 'gemini-test']);

    Generate::text('Hello')
        ->model(Google::model('gemini-3.5-flash'))
        ->instructions('You are concise.')
        ->reasoning(Reasoning::effort('low'))
        ->run();

    $body = $client->sentBody();
    expect($body['system_instruction'])->toBe('You are concise.')
        ->and($body['generation_config']['thinking_level'])->toBe('low');
});

it('loads model capabilities from resources models json', function () {
    Google::create(['apiKey' => 'gemini-test']);

    expect(Google::model('gemini-3.5-flash')->supports(Capability::Reasoning))->toBeTrue()
        ->and(Google::model('gemini-2.0-flash')->supports(Capability::ImageInput))->toBeTrue()
        ->and(Google::image('gemini-3.1-flash-image')->supports(Capability::ImageGeneration))->toBeTrue();
});
