# aisdk/google

<a href="https://github.com/phpaisdk/google/actions"><img alt="GitHub Workflow Status" src="https://img.shields.io/github/actions/workflow/status/phpaisdk/google/tests.yml?branch=main&label=Tests"></a>
<a href="https://packagist.org/packages/aisdk/google"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/aisdk/google"></a>
<a href="https://packagist.org/packages/aisdk/google"><img alt="Latest Version" src="https://img.shields.io/packagist/v/aisdk/google"></a>
<a href="https://packagist.org/packages/aisdk/google"><img alt="License" src="https://img.shields.io/packagist/l/aisdk/google"></a>
<a href="https://whyphp.dev"><img src="https://img.shields.io/badge/Why_PHP-in_2026-7A86E8?style=flat-square&labelColor=18181b" alt="Why PHP in 2026"></a>

------

Official Google Gemini provider for the PHP AI SDK.

## Installation

```bash
composer require aisdk/google
```

## Basic Usage

```php
use AiSdk\Generate;
use AiSdk\Google;

$result = Generate::text()
    ->model(Google::model('gemini-3.5-flash'))
    ->instructions('Write short, clear answers.')
    ->prompt('Explain closures in PHP.')
    ->run();

echo $result->text;
```

Google model IDs pass through unchanged and do not need to be registered. This package does not ship a model inventory; the SDK performs internal adapter validation before Google validates support for the selected model.

## Configuration

| Variable | Description | Default |
|---|---|---|
| `GOOGLE_GENERATIVE_AI_API_KEY` | API key for authentication | Required |
| `GEMINI_API_KEY` | Fallback API key variable | Required |
| `GOOGLE_GENERATIVE_AI_BASE_URL` | Base URL for API requests | `https://generativelanguage.googleapis.com/v1beta` |

```php
Google::create([
    'apiKey' => '...',
    'baseUrl' => 'https://generativelanguage.googleapis.com/v1beta',
]);
```

## Embeddings

```php
$result = Generate::embedding([
    'PHP is a programming language.',
    'Laravel is a PHP framework.',
])
    ->model(Google::model('gemini-embedding-001'))
    ->dimensions(768)
    ->providerOptions('google', [
        'embedContentConfig' => [
            'taskType' => 'RETRIEVAL_DOCUMENT',
            'autoTruncate' => true,
        ],
    ])
    ->run();

$vector = $result->embeddings[0]->vector;
```

The package uses `embedContent` for one text input and `batchEmbedContents` for multiple text inputs. Native Google embedding configuration can be passed through `embedContentConfig`; the portable `dimensions()` option maps to `outputDimensionality`.

## Streaming

```php
$stream = Generate::text('Tell me a story.')
    ->model(Google::model('gemini-3.5-flash'))
    ->stream();

foreach ($stream->chunks() as $chunk) {
    echo $chunk;
}

$result = $stream->run();
```

## Image Generation

```php
use AiSdk\Generate;
use AiSdk\Google;

$result = Generate::image()
    ->model(Google::model('gemini-3.1-flash-image'))
    ->prompt('A clean app icon for a PHP AI SDK')
    ->aspectRatio('1:1')
    ->run();

$result->output->save(__DIR__.'/icon.png');
```

## Speech Generation

```php
use AiSdk\Generate;
use AiSdk\Google;

$result = Generate::speech()
    ->model(Google::model('gemini-3.1-flash-tts-preview'))
    ->input('Say cheerfully: Have a wonderful day!')
    ->voice('Kore')
    ->run();

$result->output->save(__DIR__.'/speech.wav');
```

Google speech generation uses Gemini Interactions API audio responses. You can pass native speech configuration through provider options:

```php
$result = Generate::speech('Read this as a short dialogue.')
    ->model(Google::model('gemini-3.1-flash-tts-preview'))
    ->providerOptions('google', [
        'generation_config' => [
            'speech_config' => [
                ['speaker' => 'Joe', 'voice' => 'Kore'],
                ['speaker' => 'Jane', 'voice' => 'Puck'],
            ],
        ],
    ])
    ->run();
```

## Transcription

Gemini transcribes audio through its multimodal audio-understanding capability:

```php
use AiSdk\Content;
use AiSdk\Generate;
use AiSdk\Google;

$result = Generate::transcription(Content::audio(__DIR__.'/meeting.mp3'))
    ->model(Google::model('gemini-3.5-flash'))
    ->run();

echo $result->output->text;
```

## Live voice and translation

Install `aisdk/transport` when you want a ready-made WebSocket transport:

```bash
composer require aisdk/transport
```

```php
use AiSdk\Google;
use AiSdk\Live;
use AiSdk\Live\AudioDelta;
use AiSdk\Live\TranscriptDelta;
use AiSdk\Transport;

$session = Live::voice()
    ->model(Google::model('gemini-3.1-flash-live-preview'))
    ->instructions('You are a concise customer-support agent.')
    ->voice('Kore')
    ->language('en-US')
    ->connect(Transport::auto());

// Send 16 kHz mono PCM chunks while reading events concurrently.
$session->sendAudio($pcmBytes);

foreach ($session->events() as $event) {
    if ($event instanceof AudioDelta) {
        playAudio($event->bytes);
    }

    if ($event instanceof TranscriptDelta) {
        echo $event->delta;
    }
}
```

`LiveSession` also supports text input, audio-stream completion, tool results,
and explicit closure. Tools registered with `->tools([...])` are executed by
core when they have a handler; unknown tool calls remain available as
`ToolCallEvent` values.

Provider-only setup fields, including session resumption, remain available
without enlarging the portable API:

```php
->providerOptions('google', [
    'raw' => ['sessionResumption' => ['handle' => $resumeHandle]],
])
```

Gemini resumption updates and other unrecognized messages are preserved as
`ProviderEvent` values.

Gemini's dedicated Live Translate model has its own setup schema and accepts
audio input only:

```php
$translator = Live::translate()
    ->model(Google::model('gemini-3.5-live-translate-preview'))
    ->from('en')
    ->to('es')
    ->connect(Transport::auto());

$translator->sendAudio($pcmBytes);
```

Gemini Live does not expose a standalone streaming-transcription session.
Voice and translation sessions still emit transcript events; use
`Generate::transcription()` for a standalone transcription request.

### Core-only transport

`aisdk/transport` is optional. An application transport can implement
`AiSdk\Live\Contracts\TransportInterface` and
`TransportConnectionInterface`, then be passed to the same builder:

```php
$session = Live::voice()
    ->model(Google::model('gemini-3.1-flash-live-preview'))
    ->connect($appWebSocketTransport);
```

The [core custom-transport guide](https://github.com/phpaisdk/core#core-without-aisdktransport)
contains the complete WebSocket implementation. Provider JSON remains in this
package; the custom transport only sends and receives text or binary frames.

### Browser credentials

Create a constrained, short-lived Gemini token on your backend instead of
exposing the API key in a browser:

```php
$secret = Live::voice()
    ->model(Google::model('gemini-3.1-flash-live-preview'))
    ->voice('Kore')
    ->clientSecret();

return ['token' => $secret->value, 'expires_at' => $secret->expiresAt];
```

The browser uses that token with Gemini's constrained `v1alpha` Live WebSocket.
Gemini Live currently uses WebSockets; this package does not advertise a PHP
WebRTC or SIP lifecycle for Google.

## Provider-Specific Options

```php
$result = Generate::text('Hello')
    ->model(Google::model('gemini-3.5-flash'))
    ->providerOptions('google', [
        'raw' => [
            'generation_config' => ['temperature' => 0.2],
            'store' => false,
        ],
    ])
    ->run();
```

## Video Generation

```php
$result = Generate::video('A cinematic ocean scene')
    ->model(Google::model('veo-3.1-generate-preview'))
    ->aspectRatio('16:9')
    ->resolution('1280x720')
    ->duration(8)
    ->run(timeout: 600);
```

## Testing

```bash
composer test
```

The default suite uses protocol fixtures and conformance checks. Credentialed
Live network verification is separate from the default test run.

## Documentation

- [PHP AI SDK documentation](https://phpaisdk.com/docs)
- [Google documentation](https://phpaisdk.com/docs/google)

## Community

- [Contributing](https://github.com/phpaisdk/.github/blob/main/CONTRIBUTING.md)
- [Support](https://github.com/phpaisdk/.github/blob/main/SUPPORT.md)
- For private security reports, email [security@phpaisdk.com](mailto:security@phpaisdk.com).
