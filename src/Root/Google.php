<?php

declare(strict_types=1);

namespace AiSdk;

use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Contracts\SpeechModelInterface;
use AiSdk\Contracts\TextModelInterface;
use AiSdk\Google\GoogleOptions;
use AiSdk\Google\GoogleProvider;
use AiSdk\Support\Concerns\RegistersModels;

final class Google
{
    use RegistersModels;

    private static ?GoogleProvider $default = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public static function create(array $config = []): GoogleProvider
    {
        return self::$default = new GoogleProvider(GoogleOptions::fromArray($config));
    }

    public static function default(): GoogleProvider
    {
        return self::$default ??= self::create();
    }

    public static function reset(): void
    {
        self::$default = null;
    }

    public static function model(string $modelId): TextModelInterface
    {
        return self::default()->textModel($modelId);
    }

    public static function image(string $modelId): ImageModelInterface
    {
        return self::default()->imageModel($modelId);
    }

    public static function speech(string $modelId): SpeechModelInterface
    {
        return self::default()->speechModel($modelId);
    }
}
