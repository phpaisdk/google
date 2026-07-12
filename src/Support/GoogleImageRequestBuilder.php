<?php

declare(strict_types=1);

namespace AiSdk\Google\Support;

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Requests\ImageRequest;

final class GoogleImageRequestBuilder
{
    /**
     * @return array<string, mixed>
     */
    public static function build(string $providerName, string $modelId, ImageRequest $request): array
    {
        if ($request->count !== 1) {
            throw new InvalidArgumentException('Google image generation does not support the portable count() option. Run multiple requests or use provider-specific options when Google exposes multi-image output.');
        }

        if ($request->seed !== null) {
            throw new InvalidArgumentException('Google image generation does not support the portable seed() option.');
        }

        $responseFormat = ['type' => 'image'];
        if ($request->aspectRatio !== null) {
            $responseFormat['aspect_ratio'] = $request->aspectRatio;
        }
        if ($request->size !== null) {
            $responseFormat['image_size'] = self::imageSize($request->size);
        }

        $body = [
            'model' => $modelId,
            'input' => [['type' => 'text', 'text' => $request->prompt]],
            'response_format' => $responseFormat,
        ];

        $raw = $request->providerOptionsFor($providerName)['raw'] ?? null;
        if (is_array($raw)) {
            $body = array_replace_recursive($body, $raw);
        }

        return $body;
    }

    private static function imageSize(string $size): string
    {
        [$width, $height] = array_map('intval', explode('x', $size, 2));
        $longest = max($width, $height);

        return $longest > 1024 ? '2K' : '1K';
    }
}
