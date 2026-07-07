<?php

declare(strict_types=1);

namespace AiSdk\Google\Support;

use AiSdk\FinishReason;
use AiSdk\Responses\Parts\ReasoningPart;
use AiSdk\Responses\Parts\TextPart;
use AiSdk\Responses\Parts\ToolCallPart;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Support\Usage;

final class GoogleResponseParser
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function parse(array $payload): TextModelResponse
    {
        $parts = [];
        $outputText = $payload['output_text'] ?? $payload['outputText'] ?? null;

        if (is_string($outputText) && $outputText !== '') {
            $parts[] = new TextPart($outputText);
        }

        if ($parts === []) {
            foreach (self::responseContent($payload) as $part) {
                if (($part['type'] ?? null) === 'text' && isset($part['text']) && is_string($part['text'])) {
                    $parts[] = new TextPart($part['text']);
                }

                if (($part['type'] ?? null) === 'thought' && isset($part['text']) && is_string($part['text'])) {
                    $parts[] = new ReasoningPart($part['text']);
                }

                if (($part['type'] ?? null) === 'function_call') {
                    $parts[] = new ToolCallPart(
                        id: (string) ($part['id'] ?? $part['call_id'] ?? ''),
                        name: (string) ($part['name'] ?? ''),
                        arguments: is_array($part['args'] ?? null) ? $part['args'] : [],
                    );
                }
            }
        }

        return new TextModelResponse(
            parts: $parts,
            finishReason: self::finishReason($payload['finish_reason'] ?? $payload['finishReason'] ?? null, $parts),
            usage: self::usage($payload),
            rawResponse: $payload,
            providerMetadata: ['google' => self::metadata($payload)],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private static function responseContent(array $payload): array
    {
        $steps = $payload['steps'] ?? [];
        if (! is_array($steps)) {
            return [];
        }

        $content = [];
        foreach ($steps as $step) {
            if (! is_array($step)) {
                continue;
            }

            $stepContent = $step['content'] ?? [];
            if (is_array($stepContent)) {
                foreach ($stepContent as $part) {
                    if (is_array($part)) {
                        $content[] = $part;
                    }
                }
            }
        }

        return $content;
    }

    /**
     * @param  array<int, object>  $parts
     */
    private static function finishReason(mixed $raw, array $parts): FinishReason
    {
        foreach ($parts as $part) {
            if ($part instanceof ToolCallPart) {
                return FinishReason::ToolCalls;
            }
        }

        return match (strtolower((string) $raw)) {
            'stop', 'stopped', 'end_turn' => FinishReason::Stop,
            'max_tokens', 'max_output_tokens', 'length' => FinishReason::Length,
            'safety', 'content_filter', 'blocked' => FinishReason::ContentFilter,
            default => $raw === null ? FinishReason::Stop : FinishReason::Unknown,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function usage(array $payload): Usage
    {
        $usage = $payload['usage_metadata'] ?? $payload['usageMetadata'] ?? $payload['usage'] ?? [];
        if (! is_array($usage)) {
            return Usage::empty();
        }

        $input = self::intValue($usage, 'input_tokens', 'inputTokens', 'promptTokenCount', 'prompt_token_count') ?? 0;
        $output = self::intValue($usage, 'output_tokens', 'outputTokens', 'candidatesTokenCount', 'candidates_token_count') ?? 0;
        $reasoning = self::intValue($usage, 'reasoning_tokens', 'reasoningTokens', 'thoughtsTokenCount', 'thoughts_token_count') ?? 0;
        $cached = self::intValue($usage, 'cachedContentTokenCount', 'cached_content_token_count', 'cachedInputTokens', 'cached_input_tokens');
        $total = self::intValue($usage, 'total_tokens', 'totalTokens', 'totalTokenCount', 'total_token_count');

        return new Usage(
            inputTokens: $input,
            outputTokens: $output + $reasoning,
            totalTokens: $total,
            reasoningTokens: $reasoning > 0 ? $reasoning : null,
            cachedInputTokens: $cached,
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  non-empty-string  ...$keys
     */
    private static function intValue(array $values, string ...$keys): ?int
    {
        foreach ($keys as $key) {
            if (isset($values[$key]) && is_numeric($values[$key])) {
                return (int) $values[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function metadata(array $payload): array
    {
        $metadata = [];
        foreach (['id', 'model', 'finish_reason', 'usage_metadata', 'prompt_feedback'] as $key) {
            if (array_key_exists($key, $payload)) {
                $metadata[$key] = $payload[$key];
            }
        }

        return $metadata;
    }
}
