<?php

declare(strict_types=1);

use AiSdk\Generate;
use AiSdk\Google;
use AiSdk\Google\Tests\Fakes\FakeHttpClient;
use AiSdk\Support\Sdk;
use Nyholm\Psr7\Factory\Psr17Factory;

afterEach(function () {
    Generate::reset();
    Google::reset();
});

function configureGoogleEmbeddingsWith(FakeHttpClient $client): void
{
    $factory = new Psr17Factory;
    Generate::configure(new Sdk(
        httpClient: $client,
        requestFactory: $factory,
        streamFactory: $factory,
    ));
}

it('generates a Google embedding with the current embed content schema', function () {
    $client = new FakeHttpClient(200, json_encode([
        'embedding' => ['values' => [0.1, 0.2, 0.3]],
        'usageMetadata' => ['promptTokenCount' => 4],
    ]));
    configureGoogleEmbeddingsWith($client);
    Google::create(['apiKey' => 'gemini-test']);

    $result = Generate::embedding('PHP is a programming language.')
        ->model(Google::model('gemini-embedding-001'))
        ->dimensions(768)
        ->providerOptions('google', [
            'embedContentConfig' => [
                'taskType' => 'RETRIEVAL_DOCUMENT',
                'title' => 'PHP',
                'autoTruncate' => true,
            ],
        ])
        ->run();

    expect($result->output->vector)->toBe([0.1, 0.2, 0.3])
        ->and($result->usage->inputTokens)->toBe(4)
        ->and($result->providerMetadata['google']['model'])->toBe('gemini-embedding-001')
        ->and((string) $client->lastRequest?->getUri())->toBe('https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent')
        ->and($client->sentBody())->toBe([
            'model' => 'models/gemini-embedding-001',
            'content' => [
                'parts' => [
                    ['text' => 'PHP is a programming language.'],
                ],
            ],
            'embedContentConfig' => [
                'outputDimensionality' => 768,
                'taskType' => 'RETRIEVAL_DOCUMENT',
                'title' => 'PHP',
                'autoTruncate' => true,
            ],
        ]);
});

it('generates Google embeddings in one batch request', function () {
    $client = new FakeHttpClient(200, json_encode([
        'embeddings' => [
            ['values' => [0.1, 0.2]],
            ['values' => [0.3, 0.4]],
        ],
        'usageMetadata' => ['promptTokenCount' => 9],
    ]));
    configureGoogleEmbeddingsWith($client);
    Google::create(['apiKey' => 'gemini-test']);

    $result = Generate::embedding(['First document', 'Second document'])
        ->model(Google::model('models/gemini-embedding-001'))
        ->run();

    expect($result->embeddings[0]->vector)->toBe([0.1, 0.2])
        ->and($result->embeddings[1]->vector)->toBe([0.3, 0.4])
        ->and($result->usage->inputTokens)->toBe(9)
        ->and($client->lastRequest?->getUri()->getPath())->toBe('/v1beta/models/gemini-embedding-001:batchEmbedContents')
        ->and($client->sentBody())->toBe([
            'requests' => [
                [
                    'model' => 'models/gemini-embedding-001',
                    'content' => ['parts' => [['text' => 'First document']]],
                ],
                [
                    'model' => 'models/gemini-embedding-001',
                    'content' => ['parts' => [['text' => 'Second document']]],
                ],
            ],
        ]);
});

it('accepts opaque Google embedding model ids', function () {
    Google::create(['apiKey' => 'gemini-test']);

    expect(Google::model('future-embedding-model')->modelId())->toBe('future-embedding-model');
});
