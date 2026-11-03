# PHP AI SDK — Google Gemini

Text, image, speech, embedding, and video generation, plus live sessions with Google models. Part of the framework-agnostic [PHP AI SDK](https://phpaisdk.com).

## Installation

```bash
composer require aisdk/google
```

Set `GEMINI_API_KEY` in your environment.

## Usage

```php
use AiSdk\Generate;
use AiSdk\Google;

$result = Generate::text('Explain closures in PHP in one sentence.')
    ->model(Google::model('gemini-3.8-flash'))
    ->run();

echo $result->text;
```

## Documentation

[Read the documentation](https://phpaisdk.com/docs/0.x/providers/google) for configuration, supported features, and more examples.

[Contributing](https://github.com/phpaisdk/.github/blob/main/CONTRIBUTING.md) · [Support](https://github.com/phpaisdk/.github/blob/main/SUPPORT.md)

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
