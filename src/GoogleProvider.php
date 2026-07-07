<?php

declare(strict_types=1);

namespace AiSdk\Google;

use AiSdk\Contracts\BaseProvider;
use AiSdk\Contracts\TextModelInterface;
use AiSdk\Google\Models\GoogleTextModel;

final class GoogleProvider extends BaseProvider
{
    public function __construct(public readonly GoogleOptions $options) {}

    public function name(): string
    {
        return GoogleOptions::PROVIDER_NAME;
    }

    public function textModel(string $modelId): TextModelInterface
    {
        return new GoogleTextModel($modelId, $this->options, $this->modelRegistry());
    }
}
