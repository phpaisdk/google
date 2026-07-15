<?php

declare(strict_types=1);

namespace AiSdk\Google\Live;

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Google\GoogleOptions;
use AiSdk\Live\LiveOperation;
use AiSdk\Live\LiveRequest;
use AiSdk\Tool;

/** Maps the provider-neutral Live request to Gemini's setup message. */
final class GoogleLiveConfiguration
{
    /** @return array<string, mixed> */
    public static function setup(string $modelId, LiveRequest $request): array
    {
        if ($request->operation === LiveOperation::Transcribe) {
            throw new InvalidArgumentException(
                'Google Gemini Live does not provide a standalone transcription session. Use Live::voice() and consume transcript events, or use Google Cloud Speech-to-Text.',
                ['provider' => GoogleOptions::PROVIDER_NAME, 'modelId' => $modelId],
            );
        }

        $setup = [
            'model' => 'models/'.$modelId,
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
            ],
            'inputAudioTranscription' => new \stdClass,
            'outputAudioTranscription' => new \stdClass,
        ];

        $instructions = $request->options['instructions'] ?? null;
        if (is_string($instructions) && $instructions !== '') {
            $setup['systemInstruction'] = ['parts' => [['text' => $instructions]]];
        }

        $voice = $request->options['voice'] ?? null;
        if (is_string($voice) && $voice !== '') {
            $setup['generationConfig']['speechConfig']['voiceConfig'] = [
                'prebuiltVoiceConfig' => ['voiceName' => $voice],
            ];
        }

        $language = $request->options['language'] ?? null;
        if (is_string($language) && $language !== '') {
            $setup['generationConfig']['speechConfig']['languageCode'] = $language;
        }

        if (array_key_exists('turn_detection', $request->options)) {
            $setup['realtimeInputConfig']['automaticActivityDetection'] = self::turnDetection(
                $request->options['turn_detection'],
            );
        }

        if ($request->tools !== []) {
            $setup['tools'] = [[
                'functionDeclarations' => array_map(
                    static fn (Tool $tool): array => [
                        'name' => $tool->name(),
                        'description' => $tool->description(),
                        'parameters' => $tool->inputSchemaForProvider(),
                    ],
                    $request->tools,
                ),
            ]];
        }

        if ($request->operation === LiveOperation::Translate) {
            $target = $request->options['to'] ?? null;
            if (! is_string($target) || $target === '') {
                throw new InvalidArgumentException('Live::translate() requires to().');
            }

            $provider = $request->providerOptions[GoogleOptions::PROVIDER_NAME] ?? [];
            $echo = $provider['echoTargetLanguage'] ?? $provider['echo_target_language'] ?? false;
            $setup['generationConfig']['inputAudioTranscription'] = new \stdClass;
            $setup['generationConfig']['outputAudioTranscription'] = new \stdClass;
            $setup['generationConfig']['translationConfig'] = [
                'targetLanguageCode' => $target,
                'echoTargetLanguage' => (bool) $echo,
            ];
            unset(
                $setup['inputAudioTranscription'],
                $setup['outputAudioTranscription'],
                $setup['systemInstruction'],
                $setup['tools'],
                $setup['generationConfig']['speechConfig'],
            );
        }

        $providerOptions = $request->providerOptions[GoogleOptions::PROVIDER_NAME] ?? [];
        $raw = $providerOptions['raw'] ?? null;

        return is_array($raw) ? array_replace_recursive($setup, $raw) : $setup;
    }

    /** @return array<string, mixed> */
    private static function turnDetection(mixed $value): array
    {
        if ($value === null || $value === 'none' || $value === 'disabled') {
            return ['disabled' => true];
        }

        if (is_string($value)) {
            return [];
        }

        if (! is_array($value)) {
            return [];
        }

        $mapping = [
            'start_of_speech_sensitivity' => 'startOfSpeechSensitivity',
            'prefix_padding_ms' => 'prefixPaddingMs',
            'end_of_speech_sensitivity' => 'endOfSpeechSensitivity',
            'silence_duration_ms' => 'silenceDurationMs',
        ];
        $normalized = [];
        foreach ($value as $key => $setting) {
            if (! is_string($key) || $key === 'type') {
                continue;
            }

            $normalized[$mapping[$key] ?? $key] = $setting;
        }

        return $normalized;
    }
}
