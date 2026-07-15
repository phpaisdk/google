<?php

declare(strict_types=1);

namespace AiSdk;

use AiSdk\Contracts\Model;
use AiSdk\Google\GoogleOptions;
use AiSdk\Google\GoogleProvider;

final class Google
{
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

    public static function model(string $modelId): Model
    {
        return self::default()->model($modelId);
    }
}
