<?php

declare(strict_types=1);

namespace AiSdk\Google\Support;

use AiSdk\FinishReason;
use AiSdk\Streaming\FinishPart;
use AiSdk\Streaming\ProviderMetadataPart;
use AiSdk\Streaming\ReasoningDeltaPart;
use AiSdk\Streaming\StreamPart;
use AiSdk\Streaming\TextDeltaPart;
use AiSdk\Streaming\ToolCallDeltaPart;
use AiSdk\Streaming\ToolCallStartPart;
use AiSdk\Support\Usage;
use Generator;

final class GoogleStreamParser
{
    /**
     * @param  iterable<int, array{event: ?string, data: string}>  $events
     * @return Generator<int, StreamPart>
     */
    public static function parse(iterable $events): Generator
    {
        $usage = Usage::empty();
        $finishReason = FinishReason::Stop;
        $metadata = [];
        $toolIndex = 0;

        foreach ($events as $event) {
            $data = $event['data'];
            if ($data === '' || $data === '[DONE]') {
                continue;
            }

            $payload = json_decode($data, true);
            if (! is_array($payload)) {
                continue;
            }

            if (isset($payload['usage_metadata']) || isset($payload['usageMetadata']) || isset($payload['usage'])) {
                $usage = GoogleResponseParser::usage($payload);
            }

            foreach (['id', 'model', 'finish_reason', 'usage_metadata'] as $key) {
                if (array_key_exists($key, $payload)) {
                    $metadata[$key] = $payload[$key];
                }
            }

            if (isset($payload['finish_reason'])) {
                $finishReason = self::finishReason($payload['finish_reason']);
            }

            $delta = $payload['delta'] ?? null;
            if (is_array($delta)) {
                yield from self::yieldDelta($delta, $toolIndex, $finishReason);
            }
        }

        if ($metadata !== []) {
            yield new ProviderMetadataPart('google', $metadata);
        }

        yield new FinishPart($finishReason, $usage);
    }

    /**
     * @return Generator<int, StreamPart>
     */
    /**
     * @param  array<string, mixed>  $delta
     * @return Generator<int, StreamPart>
     */
    private static function yieldDelta(array $delta, int &$toolIndex, FinishReason &$finishReason): Generator
    {
        if (($delta['type'] ?? null) === 'text' && isset($delta['text']) && is_string($delta['text']) && $delta['text'] !== '') {
            yield new TextDeltaPart($delta['text']);
        }

        if (($delta['type'] ?? null) === 'thought' && isset($delta['text']) && is_string($delta['text']) && $delta['text'] !== '') {
            yield new ReasoningDeltaPart($delta['text']);
        }

        if (($delta['type'] ?? null) === 'function_call') {
            $index = $toolIndex++;
            $id = (string) ($delta['id'] ?? $delta['call_id'] ?? "call_{$index}");
            $name = (string) ($delta['name'] ?? '');
            $args = json_encode($delta['args'] ?? new \stdClass, JSON_THROW_ON_ERROR);

            $finishReason = FinishReason::ToolCalls;
            yield new ToolCallStartPart($index, $id, $name);
            yield new ToolCallDeltaPart($index, $args, $id, $name);
        }
    }

    private static function finishReason(mixed $raw): FinishReason
    {
        return match (strtolower((string) $raw)) {
            'stop', 'stopped', 'end_turn' => FinishReason::Stop,
            'max_tokens', 'max_output_tokens', 'length' => FinishReason::Length,
            'safety', 'content_filter', 'blocked' => FinishReason::ContentFilter,
            default => FinishReason::Unknown,
        };
    }
}
