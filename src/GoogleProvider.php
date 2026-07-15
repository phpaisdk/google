<?php

declare(strict_types=1);

namespace AiSdk\Google;

use AiSdk\Contracts\BaseProvider;
use AiSdk\Contracts\EmbeddingModelInterface;
use AiSdk\Contracts\EmbeddingProviderInterface;
use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Contracts\ImageProviderInterface;
use AiSdk\Contracts\LiveProviderInterface;
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
use AiSdk\Google\Models\GoogleLiveModel;
use AiSdk\Google\Models\GoogleSpeechModel;
use AiSdk\Google\Models\GoogleTextModel;
use AiSdk\Google\Models\GoogleTranscriptionModel;
use AiSdk\Google\Models\GoogleVideoModel;
use AiSdk\Live\Contracts\LiveModelInterface;

final class GoogleProvider extends BaseProvider implements EmbeddingProviderInterface, ImageProviderInterface, LiveProviderInterface, SpeechProviderInterface, TextProviderInterface, TranscriptionProviderInterface, VideoProviderInterface
{
    public function __construct(public readonly GoogleOptions $options) {}

    public function name(): string
    {
        return GoogleOptions::PROVIDER_NAME;
    }

    protected function textModel(string $modelId): TextModelInterface
    {
        return new GoogleTextModel($modelId, $this->options);
    }

    protected function imageModel(string $modelId): ImageModelInterface
    {
        return new GoogleImageModel($modelId, $this->options);
    }

    protected function speechModel(string $modelId): SpeechModelInterface
    {
        return new GoogleSpeechModel($modelId, $this->options);
    }

    protected function transcriptionModel(string $modelId): TranscriptionModelInterface
    {
        return new GoogleTranscriptionModel($modelId, $this->options);
    }

    protected function embeddingModel(string $modelId): EmbeddingModelInterface
    {
        return new GoogleEmbeddingModel($modelId, $this->options);
    }

    protected function videoModel(string $modelId): VideoModelInterface
    {
        return new GoogleVideoModel($modelId, $this->options);
    }

    protected function liveModel(string $modelId): LiveModelInterface
    {
        return new GoogleLiveModel($modelId, $this->options);
    }
}
