# aisdk/google

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
    ->model(Google::embedding('gemini-embedding-001'))
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
    ->model(Google::image('gemini-3.1-flash-image'))
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
    ->model(Google::speech('gemini-3.1-flash-tts-preview'))
    ->input('Say cheerfully: Have a wonderful day!')
    ->voice('Kore')
    ->run();

$result->output->save(__DIR__.'/speech.wav');
```

Google speech generation uses Gemini Interactions API audio responses. You can pass native speech configuration through provider options:

```php
$result = Generate::speech('Read this as a short dialogue.')
    ->model(Google::speech('gemini-3.1-flash-tts-preview'))
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

## Testing

```bash
composer test
```

## Links

- [Google Embeddings API](https://ai.google.dev/api/embeddings)
- [Core Package](https://github.com/phpaisdk/core)
