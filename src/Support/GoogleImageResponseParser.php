<?php

declare(strict_types=1);

namespace AiSdk\Google\Support;

use AiSdk\Exceptions\InvalidResponseException;
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

        $image = $payload['output_image'] ?? $payload['outputImage'] ?? null;
        if (is_array($image) && is_string($image['data'] ?? null) && base64_decode($image['data'], strict: true) !== false) {
            $images[] = new ImageData(
                base64: $image['data'],
                mimeType: is_string($image['mime_type'] ?? null)
                    ? $image['mime_type']
                    : (is_string($image['mimeType'] ?? null) ? $image['mimeType'] : 'image/png'),
            );
        }

        if ($images === []) {
            throw InvalidResponseException::forProvider($providerName, 'Google returned no generated image.', ['body' => $payload]);
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
