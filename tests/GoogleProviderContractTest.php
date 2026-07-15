<?php

declare(strict_types=1);

use AiSdk\Contracts\EmbeddingProviderInterface;
use AiSdk\Contracts\ImageProviderInterface;
use AiSdk\Contracts\LiveProviderInterface;
use AiSdk\Contracts\SpeechProviderInterface;
use AiSdk\Contracts\TextProviderInterface;
use AiSdk\Contracts\TranscriptionProviderInterface;
use AiSdk\Contracts\VideoProviderInterface;
use AiSdk\Google;

afterEach(function () {
    Google::reset();
});

it('advertises every implemented Google provider contract', function () {
    $provider = Google::create(['apiKey' => 'gemini-test']);

    expect($provider)->toBeInstanceOf(TextProviderInterface::class)
        ->and($provider)->toBeInstanceOf(ImageProviderInterface::class)
        ->and($provider)->toBeInstanceOf(LiveProviderInterface::class)
        ->and($provider)->toBeInstanceOf(SpeechProviderInterface::class)
        ->and($provider)->toBeInstanceOf(TranscriptionProviderInterface::class)
        ->and($provider)->toBeInstanceOf(EmbeddingProviderInterface::class)
        ->and($provider)->toBeInstanceOf(VideoProviderInterface::class)
        ->and($provider->model('gemini-test')->provider())->toBe('google');
});
