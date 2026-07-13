<?php

declare(strict_types=1);

use AiSdk\Content;
use AiSdk\Generate;
use AiSdk\Google;
use AiSdk\Google\Tests\Fakes\FakeHttpClient;
use AiSdk\Support\Sdk;
use Nyholm\Psr7\Factory\Psr17Factory;

afterEach(function () {
    Generate::reset();
    Google::reset();
});

it('transcribes through Gemini audio understanding', function () {
    $client = new FakeHttpClient(200, '{"output_text":"Gemini transcript.","usage":{"input_tokens":8,"output_tokens":3}}');
    $factory = new Psr17Factory;
    Generate::configure(new Sdk($client, $factory, $factory));
    Google::create(['apiKey' => 'google-test']);

    $result = Generate::transcription(Content::audio('wav-bytes', 'audio/wav', 'clip.wav'))
        ->model(Google::transcription('gemini-2.5-flash'))
        ->run();

    $body = $client->sentBody();
    expect($result->output->text)->toBe('Gemini transcript.')
        ->and((string) $client->lastRequest?->getUri())->toBe('https://generativelanguage.googleapis.com/v1beta/interactions')
        ->and($body['model'])->toBe('gemini-2.5-flash')
        ->and($body['input'][0]['content'][0]['text'])->toContain('Transcribe')
        ->and($body['input'][0]['content'][1]['inline_data'])->toBe([
            'mime_type' => 'audio/wav',
            'data' => base64_encode('wav-bytes'),
        ]);
});
