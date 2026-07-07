<?php

declare(strict_types=1);

namespace AiSdk\Google\Support;

use AiSdk\Responses\ImageResponse;
use AiSdk\Results\ImageData;

final class GoogleImageResponseParser
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function parse(array $payload, string $providerName): ImageResponse
    {
        $images = [];

        foreach (self::parts($payload) as $part) {
            $inlineData = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (! is_array($inlineData) || ! is_string($inlineData['data'] ?? null)) {
                continue;
            }

            $images[] = new ImageData(
                base64: $inlineData['data'],
                mimeType: is_string($inlineData['mimeType'] ?? null)
                    ? $inlineData['mimeType']
                    : (is_string($inlineData['mime_type'] ?? null) ? $inlineData['mime_type'] : 'image/png'),
            );
        }

        return new ImageResponse(
            images: $images,
            usage: GoogleResponseParser::usage($payload),
            rawResponse: $payload,
            providerMetadata: [$providerName => self::metadata($payload)],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private static function parts(array $payload): array
    {
        $parts = [];
        foreach (($payload['candidates'] ?? []) as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            foreach (($candidate['content']['parts'] ?? []) as $part) {
                if (is_array($part)) {
                    $parts[] = $part;
                }
            }
        }

        return $parts;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function metadata(array $payload): array
    {
        $metadata = [];
        foreach (['model', 'promptFeedback', 'prompt_feedback', 'usageMetadata', 'usage_metadata'] as $key) {
            if (array_key_exists($key, $payload)) {
                $metadata[$key] = $payload[$key];
            }
        }

        return $metadata;
    }
}
