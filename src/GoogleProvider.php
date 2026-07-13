<?php

declare(strict_types=1);

namespace AiSdk\Google;

use AiSdk\Contracts\BaseProvider;
use AiSdk\Contracts\EmbeddingModelInterface;
use AiSdk\Contracts\EmbeddingProviderInterface;
use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Contracts\ImageProviderInterface;
use AiSdk\Contracts\SpeechModelInterface;
use AiSdk\Contracts\SpeechProviderInterface;
use AiSdk\Contracts\TextModelInterface;
use AiSdk\Contracts\TextProviderInterface;
use AiSdk\Contracts\TranscriptionModelInterface;
use AiSdk\Contracts\TranscriptionProviderInterface;
use AiSdk\Contracts\VideoModelInterface;
use AiSdk\Contracts\VideoProviderInterface;
use AiSdk\Google\Models\GoogleEmbeddingModel;
use AiSdk\Google\Models\GoogleImageModel;
use AiSdk\Google\Models\GoogleSpeechModel;
use AiSdk\Google\Models\GoogleTextModel;
use AiSdk\Google\Models\GoogleTranscriptionModel;
use AiSdk\Google\Models\GoogleVideoModel;

final class GoogleProvider extends BaseProvider implements EmbeddingProviderInterface, ImageProviderInterface, SpeechProviderInterface, TextProviderInterface, TranscriptionProviderInterface, VideoProviderInterface
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

    public function transcriptionModel(string $modelId): TranscriptionModelInterface
    {
        return new GoogleTranscriptionModel($modelId, $this->options);
    }

    public function embeddingModel(string $modelId): EmbeddingModelInterface
    {
        return new GoogleEmbeddingModel($modelId, $this->options);
    }

    public function videoModel(string $modelId): VideoModelInterface
    {
        return new GoogleVideoModel($modelId, $this->options);
    }
}
