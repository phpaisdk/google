<?php

declare(strict_types=1);

namespace AiSdk\Google\Models;

use AiSdk\Content;
use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\TranscriptionModelInterface;
use AiSdk\Google\GoogleOptions;
use AiSdk\Message;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Requests\TranscriptionRequest;
use AiSdk\Responses\TranscriptionResponse;
use AiSdk\Results\TranscriptData;

final class GoogleTranscriptionModel extends BaseModel implements TranscriptionModelInterface
{
    private const string PROMPT = 'Transcribe the supplied audio accurately. Return only the transcript text.';

    public function __construct(
        private readonly string $modelId,
        private readonly GoogleOptions $options,
    ) {}

    public function provider(): string
    {
        return GoogleOptions::PROVIDER_NAME;
    }

    public function modelId(): string
    {
        return $this->modelId;
    }

    public function transcribe(TranscriptionRequest $request): TranscriptionResponse
    {
        $response = (new GoogleTextModel($this->modelId, $this->options))->generate(new TextModelRequest(
            messages: [Message::user([
                Content::text(self::PROMPT),
                $request->audio,
            ])],
            maxTokens: 4096,
            temperature: 0.0,
            providerOptions: $request->providerOptions,
        ));

        return new TranscriptionResponse(
            transcript: new TranscriptData($response->text()),
            usage: $response->usage,
            rawResponse: $response->rawResponse,
            providerMetadata: $response->providerMetadata,
        );
    }
}
