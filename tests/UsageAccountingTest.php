<?php

declare(strict_types=1);

use AiSdk\Google\Support\GoogleResponseParser;
use AiSdk\Google\Support\GoogleStreamParser;

it('keeps Gemini prompt cache counts as subsets and includes thoughts in output', function (array $payload): void {
    $normal = GoogleResponseParser::usage($payload);
    $events = [
        ['event' => null, 'data' => json_encode(['event_type' => 'interaction.completed', 'interaction' => $payload], JSON_THROW_ON_ERROR)],
    ];
    $parts = iterator_to_array(GoogleStreamParser::parse($events));
    foreach ([$normal, $parts[array_key_last($parts)]->usage] as $usage) {
        expect($usage->inputTokens)->toBe(100)->and($usage->cachedInputTokens)->toBe(60)
            ->and($usage->outputTokens)->toBe(25)->and($usage->reasoningTokens)->toBe(5)
            ->and($usage->cacheWriteInputTokens)->toBeNull()->and($usage->totalTokens)->toBe(125);
    }
})->with([
    'generate content' => [['usageMetadata' => ['promptTokenCount' => 100, 'cachedContentTokenCount' => 60, 'candidatesTokenCount' => 20, 'thoughtsTokenCount' => 5]]],
    'interactions' => [['usage' => ['total_input_tokens' => 100, 'total_cached_tokens' => 60, 'total_output_tokens' => 20, 'total_thought_tokens' => 5]]],
]);

it('distinguishes missing Gemini subsets from reported zero and preserves provider total', function (): void {
    $missing = GoogleResponseParser::usage(['usage' => ['total_input_tokens' => 100]]);
    expect($missing->cachedInputTokens)->toBeNull()->and($missing->reasoningTokens)->toBeNull();
    $zero = GoogleResponseParser::usage(['usage' => ['total_input_tokens' => 100, 'total_cached_tokens' => 0, 'total_thought_tokens' => 0, 'total_tokens' => 999]]);
    expect($zero->cachedInputTokens)->toBe(0)->and($zero->reasoningTokens)->toBe(0)->and($zero->totalTokens)->toBe(999);
});
