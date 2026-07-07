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
