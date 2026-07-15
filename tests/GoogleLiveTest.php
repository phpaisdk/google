<?php

declare(strict_types=1);

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Generate;
use AiSdk\Google;
use AiSdk\Google\Tests\Fakes\FakeHttpClient;
use AiSdk\Google\Tests\Fakes\FakeLiveTransport;
use AiSdk\Live;
use AiSdk\Live\AudioDelta;
use AiSdk\Live\LiveClosed;
use AiSdk\Live\ProviderEvent;
use AiSdk\Live\ResponseCompleted;
use AiSdk\Live\TextDelta;
use AiSdk\Live\ToolCallEvent;
use AiSdk\Live\TranscriptDelta;
use AiSdk\Live\TranscriptSource;
use AiSdk\Live\TransportFrame;
use AiSdk\Live\WebSocketEndpoint;
use AiSdk\Schema;
use AiSdk\Support\Sdk;
use AiSdk\Tool;
use Nyholm\Psr7\Factory\Psr17Factory;

afterEach(function () {
    Generate::reset();
    Google::reset();
});

it('runs Gemini voice sessions over the core transport contract', function () {
    Google::create(['apiKey' => 'gemini-test']);
    $transport = new FakeLiveTransport([
        TransportFrame::text(json_encode([
            'serverContent' => [
                'inputTranscription' => ['text' => 'hello'],
                'modelTurn' => ['parts' => [
                    ['text' => 'Hi'],
                    ['inlineData' => ['data' => base64_encode('voice-bytes'), 'mimeType' => 'audio/pcm']],
                ]],
                'turnComplete' => true,
            ],
        ])),
        TransportFrame::text(json_encode([
            'toolCall' => ['functionCalls' => [[
                'id' => 'call-1',
                'name' => 'weather',
                'args' => ['city' => 'Lahore'],
            ]]],
        ])),
        TransportFrame::text(json_encode([
            'sessionResumptionUpdate' => ['newHandle' => 'resume-1', 'resumable' => true],
        ])),
    ]);
    $weather = Tool::make('weather')->for('Get the weather');

    $session = Live::voice()
        ->model(Google::model('gemini-3.1-flash-live-preview'))
        ->instructions('Be concise.')
        ->voice('Kore')
        ->language('en-US')
        ->tools([$weather])
        ->connect($transport);

    expect($transport->endpoint)->toBeInstanceOf(WebSocketEndpoint::class)
        ->and($transport->endpoint?->url)->toContain('BidiGenerateContent?key=gemini-test');

    $setup = $transport->connection->sentJson(0)['setup'];
    expect($setup['model'])->toBe('models/gemini-3.1-flash-live-preview')
        ->and($setup['systemInstruction']['parts'][0]['text'])->toBe('Be concise.')
        ->and($setup['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName'])->toBe('Kore')
        ->and($setup['inputAudioTranscription'])->toBe([])
        ->and($setup['tools'][0]['functionDeclarations'][0]['name'])->toBe('weather');

    $session->sendAudio('microphone-bytes');
    $session->sendText('Hello');
    $session->commitAudio();

    expect($transport->connection->sentJson(1)['realtimeInput']['audio']['data'])->toBe(base64_encode('microphone-bytes'))
        ->and($transport->connection->sentJson(2)['realtimeInput']['text'])->toBe('Hello')
        ->and($transport->connection->sentJson(3)['realtimeInput']['audioStreamEnd'])->toBeTrue();

    $events = iterator_to_array($session->events());
    $classes = array_map(static fn (object $event): string => $event::class, $events);
    expect($classes)->toContain(TranscriptDelta::class, TextDelta::class, AudioDelta::class, ResponseCompleted::class, ToolCallEvent::class, ProviderEvent::class, LiveClosed::class);
    $transcript = array_values(array_filter($events, static fn (object $event): bool => $event instanceof TranscriptDelta))[0];
    expect($transcript->source)->toBe(TranscriptSource::Input);

    $session->sendToolResult('call-1', ['temperature' => 31]);
    expect($transport->connection->sentJson(4)['toolResponse']['functionResponses'][0])
        ->toMatchArray(['id' => 'call-1', 'name' => 'weather', 'response' => ['result' => ['temperature' => 31]]]);
});

it('uses the dedicated Gemini Live Translate setup and audio-only input', function () {
    Google::create(['apiKey' => 'gemini-test']);
    $transport = new FakeLiveTransport;

    $session = Live::translate()
        ->model(Google::model('gemini-3.5-live-translate-preview'))
        ->from('en')
        ->to('es')
        ->providerOptions('google', ['echoTargetLanguage' => true])
        ->connect($transport);

    $setup = $transport->connection->sentJson(0)['setup'];
    expect($setup['generationConfig']['translationConfig'])->toBe([
        'targetLanguageCode' => 'es',
        'echoTargetLanguage' => true,
    ])->and($setup['generationConfig']['inputAudioTranscription'])->toBe([])
        ->and($setup)->not->toHaveKey('systemInstruction');

    $session->sendAudio('audio');
    expect($transport->connection->sentJson(1)['realtimeInput']['audio']['data'])->toBe(base64_encode('audio'));

    expect(fn () => $session->sendText('not supported'))
        ->toThrow(InvalidArgumentException::class, 'accepts audio input only');
});

it('creates constrained Gemini Live ephemeral tokens', function () {
    $client = new FakeHttpClient(200, json_encode([
        'name' => 'auth_tokens/ephemeral-token',
        'expireTime' => '2030-01-01T00:00:00Z',
    ]));
    $factory = new Psr17Factory;
    Generate::configure(new Sdk($client, $factory, $factory));
    Google::create(['apiKey' => 'gemini-test']);

    $secret = Live::voice()
        ->model(Google::model('gemini-3.1-flash-live-preview'))
        ->voice('Kore')
        ->clientSecret();

    expect($secret->value)->toBe('auth_tokens/ephemeral-token')
        ->and($secret->expiresAt)->toBe(strtotime('2030-01-01T00:00:00Z'))
        ->and($client->lastRequest?->getUri()->getPath())->toBe('/v1alpha/auth_tokens')
        ->and($client->lastRequest?->getHeaderLine('x-goog-api-key'))->toBe('gemini-test')
        ->and($client->sentBody()['uses'])->toBe(1)
        ->and($client->sentBody()['liveConnectConstraints']['model'])
        ->toBe('gemini-3.1-flash-live-preview')
        ->and($client->sentBody()['liveConnectConstraints']['config']['responseModalities'])
        ->toBe(['AUDIO'])
        ->and($client->sentBody())->not->toHaveKey('bidiGenerateContentSetup');
});

it('locks dedicated Live Translate settings into ephemeral tokens', function () {
    $client = new FakeHttpClient(200, json_encode(['name' => 'auth_tokens/translate-token']));
    $factory = new Psr17Factory;
    Generate::configure(new Sdk($client, $factory, $factory));
    Google::create(['apiKey' => 'gemini-test']);

    Live::translate()
        ->model(Google::model('gemini-3.5-live-translate-preview'))
        ->to('pl')
        ->providerOptions('google', [
            'echoTargetLanguage' => true,
            'clientSecret' => ['expireTime' => '2030-01-01T00:00:00Z'],
        ])
        ->clientSecret();

    expect($client->sentBody()['expireTime'])->toBe('2030-01-01T00:00:00Z')
        ->and($client->sentBody()['liveConnectConstraints'])->toBe([
            'model' => 'gemini-3.5-live-translate-preview',
            'config' => [
                'responseModalities' => ['AUDIO'],
                'inputAudioTranscription' => [],
                'outputAudioTranscription' => [],
                'translationConfig' => [
                    'targetLanguageCode' => 'pl',
                    'echoTargetLanguage' => true,
                ],
            ],
        ]);
});

it('does not expose ordinary Gemini Live as standalone streaming transcription', function () {
    Google::create(['apiKey' => 'gemini-test']);

    expect(fn () => Live::transcribe()
        ->model(Google::model('gemini-3.1-flash-live-preview'))
        ->connect(new FakeLiveTransport))
        ->toThrow(InvalidArgumentException::class, 'does not provide a standalone transcription session');
});

it('maps commit to Gemini manual activity boundaries when automatic VAD is disabled', function () {
    Google::create(['apiKey' => 'gemini-test']);
    $transport = new FakeLiveTransport;
    $session = Live::voice()
        ->model(Google::model('gemini-3.1-flash-live-preview'))
        ->turnDetection('disabled')
        ->connect($transport);

    $session->sendAudio('audio');
    $session->commitAudio();

    expect($transport->connection->sentJson(0)['setup']['realtimeInputConfig']['automaticActivityDetection'])
        ->toBe(['disabled' => true])
        ->and($transport->connection->sentJson(1)['realtimeInput']['activityStart'])->toBe([])
        ->and($transport->connection->sentJson(2)['realtimeInput']['audio']['data'])->toBe(base64_encode('audio'))
        ->and($transport->connection->sentJson(3)['realtimeInput']['activityEnd'])->toBe([]);
});

it('waits for setupComplete before returning a Gemini Live session', function () {
    Google::create(['apiKey' => 'gemini-test']);

    expect(fn () => Live::voice()
        ->model(Google::model('gemini-3.1-flash-live-preview'))
        ->connect(new FakeLiveTransport([], false)))
        ->toThrow(InvalidResponseException::class, 'closed before acknowledging');
});

it('returns parallel Gemini tool calls in one protocol response', function () {
    Google::create(['apiKey' => 'gemini-test']);
    $transport = new FakeLiveTransport([
        TransportFrame::text(json_encode([
            'toolCall' => ['functionCalls' => [
                ['id' => 'call-weather', 'name' => 'weather', 'args' => ['city' => 'Lahore']],
                ['id' => 'call-time', 'name' => 'time', 'args' => ['city' => 'Lahore']],
            ]],
        ])),
    ]);

    $session = Live::voice()
        ->model(Google::model('gemini-3.1-flash-live-preview'))
        ->tools([
            Tool::make('weather')->input(Schema::string('city')->required())->run(fn (string $city): string => "Sunny in {$city}"),
            Tool::make('time')->input(Schema::string('city')->required())->run(fn (string $city): string => "12:00 in {$city}"),
        ])
        ->connect($transport);

    iterator_to_array($session->events());

    expect($transport->connection->sent)->toHaveCount(2)
        ->and($transport->connection->sentJson(1)['toolResponse']['functionResponses'])->toBe([
            [
                'id' => 'call-weather',
                'response' => ['result' => 'Sunny in Lahore'],
                'name' => 'weather',
            ],
            [
                'id' => 'call-time',
                'response' => ['result' => '12:00 in Lahore'],
                'name' => 'time',
            ],
        ]);
});

it('rejects audio formats that Gemini Live cannot carry', function () {
    Google::create(['apiKey' => 'gemini-test']);

    expect(fn () => Live::voice()
        ->model(Google::model('gemini-3.1-flash-live-preview'))
        ->inputAudioFormat('mp3')
        ->connect(new FakeLiveTransport))
        ->toThrow(InvalidArgumentException::class, 'requires raw little-endian 16-bit mono PCM input');
});

it('preserves supported PCM input sample rates in Gemini audio frames', function () {
    Google::create(['apiKey' => 'gemini-test']);
    $transport = new FakeLiveTransport;
    $session = Live::voice()
        ->model(Google::model('gemini-3.1-flash-live-preview'))
        ->inputAudioFormat('audio/pcm;rate=48000')
        ->connect($transport);

    $session->sendAudio('audio');

    expect($transport->connection->sentJson(1)['realtimeInput']['audio']['mimeType'])
        ->toBe('audio/pcm;rate=48000');
});
