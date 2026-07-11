<?php

declare(strict_types=1);

namespace AiSdk\Google;

use AiSdk\Contracts\BaseProvider;
use AiSdk\Contracts\EmbeddingModelInterface;
use AiSdk\Contracts\EmbeddingProviderInterface;
use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Contracts\SpeechModelInterface;
use AiSdk\Contracts\TextModelInterface;
use AiSdk\Google\Models\GoogleEmbeddingModel;
use AiSdk\Google\Models\GoogleImageModel;
use AiSdk\Google\Models\GoogleSpeechModel;
use AiSdk\Google\Models\GoogleTextModel;

final class GoogleProvider extends BaseProvider implements EmbeddingProviderInterface
{
    public function __construct(public readonly GoogleOptions $options) {}

    public function name(): string
    {
        return GoogleOptions::PROVIDER_NAME;
    }

    public function textModel(string $modelId): TextModelInterface
    {
        return new GoogleTextModel($modelId, $this->options);
    }

    public function imageModel(string $modelId): ImageModelInterface
    {
        return new GoogleImageModel($modelId, $this->options);
    }

    public function speechModel(string $modelId): SpeechModelInterface
    {
        return new GoogleSpeechModel($modelId, $this->options);
    }

    public function embeddingModel(string $modelId): EmbeddingModelInterface
    {
        return new GoogleEmbeddingModel($modelId, $this->options);
    }
}
