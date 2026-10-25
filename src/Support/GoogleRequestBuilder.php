<?php

declare(strict_types=1);

namespace AiSdk\Google\Support;

use AiSdk\Content;
use AiSdk\ContentSource;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Message;
use AiSdk\Outputs\Output;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Tool;
use AiSdk\ToolChoice;

final class GoogleRequestBuilder
{
    /**
     * @return array<string, mixed>
     */
    public static function build(string $modelId, string $providerName, TextModelRequest $request, bool $stream): array
    {
        $generationConfig = [];

        if ($request->maxTokens !== null) {
            $generationConfig['max_output_tokens'] = $request->maxTokens;
        }

        if ($request->temperature !== null) {
            $generationConfig['temperature'] = $request->temperature;
        }

        if ($request->topP !== null) {
            $generationConfig['top_p'] = $request->topP;
        }

        if ($request->reasoning?->effort !== null) {
            $generationConfig['thinking_level'] = $request->reasoning->effort;
        }

        if ($request->output instanceof Output) {
            $generationConfig = array_replace($generationConfig, self::responseFormat($request->output));
        }

        if ($request->tools !== []) {
            $generationConfig['tool_choice'] = self::toolChoice($request->toolChoice);
        }

        $body = [
            'model' => $modelId,
            'input' => self::input($request->messages),
            'generation_config' => $generationConfig,
        ];

        if ($request->system !== null && $request->system !== '') {
            $body['system_instruction'] = $request->system;
        }

        if ($request->tools !== []) {
            $body['tools'] = array_map(self::tool(...), $request->tools);
        }

        if ($stream) {
            $body['stream'] = true;
        }

        return self::mergeOptions($body, $request->providerOptionsFor($providerName));
    }

    /**
     * @param  array<int, Message>  $messages
     * @return string|array<int, array<string, mixed>>
     */
    private static function input(array $messages): string|array
    {
        if (count($messages) === 1 && $messages[0]->role === Message::ROLE_USER) {
            $parts = self::contentParts($messages[0]->content);
            if (count($parts) === 1 && ($parts[0]['type'] ?? null) === 'text') {
                return (string) $parts[0]['text'];
            }
        }

        return array_map(self::messageStep(...), $messages);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private static function mergeOptions(array $body, array $options): array
    {
        $raw = $options['raw'] ?? null;
        unset($options['raw']);

        $body = array_replace($body, $options);

        return is_array($raw) ? array_replace($body, $raw) : $body;
    }

    /**
     * @return array<string, mixed>
     */
    private static function messageStep(Message $message): array
    {
        $type = match ($message->role) {
            Message::ROLE_ASSISTANT => 'model_response',
            Message::ROLE_TOOL => 'tool_result',
            default => 'user_input',
        };

        $step = [
            'type' => $type,
            'content' => self::contentParts($message->content),
        ];

        if ($message->toolCallId !== null) {
            $step['tool_call_id'] = $message->toolCallId;
        }

        return $step;
    }

    /**
     * @param  array<int, Content>  $contents
     * @return array<int, array<string, mixed>>
     */
    private static function contentParts(array $contents): array
    {
        return array_map(self::contentPart(...), $contents);
    }

    /**
     * @return array<string, mixed>
     */
    private static function contentPart(Content $content): array
    {
        if ($content->type === Content::TYPE_TEXT) {
            return ['type' => 'text', 'text' => (string) $content->textValue()];
        }

        $mimeType = $content->mimeType();

        if ($content->source() === ContentSource::Url) {
            return [
                'type' => self::mediaType($content),
                'uri' => (string) $content->url(),
                'mime_type' => $mimeType,
            ];
        }

        $data = $content->base64Data();
        if ($data === null) {
            throw new InvalidArgumentException('Google media input requires URL, raw, base64, or data URI content.');
        }

        return [
            'type' => self::mediaType($content),
            'inline_data' => [
                'mime_type' => $mimeType,
                'data' => $data,
            ],
        ];
    }

    private static function mediaType(Content $content): string
    {
        return match ($content->type) {
            Content::TYPE_IMAGE => 'image',
            Content::TYPE_AUDIO => 'audio',
            Content::TYPE_FILE => 'document',
            default => throw new InvalidArgumentException("Unsupported Google content type [{$content->type}]."),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function responseFormat(Output $output): array
    {
        if ($output->kind === Output::KIND_OBJECT && $output->schema !== null) {
            return [
                'response_mime_type' => 'application/json',
                'response_schema' => $output->schema->jsonSchema(),
            ];
        }

        if ($output->kind === Output::KIND_OBJECT) {
            return ['response_mime_type' => 'application/json'];
        }

        return [];
    }

    /** @return array<string, mixed> */
    private static function tool(Tool $tool): array
    {
        return [
            'type' => 'function',
            'name' => $tool->name(),
            'description' => $tool->description(),
            'parameters' => $tool->inputSchemaForProvider(),
        ];
    }

    /** @return string|array<string, array{mode: string, tools?: array<int, string>}> */
    private static function toolChoice(?ToolChoice $choice): string|array
    {
        if ($choice === null) {
            return ToolChoice::AUTO;
        }

        if ($choice->type !== ToolChoice::TOOL) {
            return $choice->type === ToolChoice::REQUIRED ? 'any' : $choice->type;
        }

        return [
            'allowed_tools' => [
                'mode' => 'any',
                'tools' => [(string) $choice->toolName],
            ],
        ];
    }
}
