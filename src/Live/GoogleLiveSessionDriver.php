<?php

declare(strict_types=1);

namespace AiSdk\Google\Live;

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Google\GoogleOptions;
use AiSdk\Live\AudioDelta;
use AiSdk\Live\Contracts\LiveSessionDriverInterface;
use AiSdk\Live\Contracts\TransportConnectionInterface;
use AiSdk\Live\Contracts\TransportInterface;
use AiSdk\Live\Interrupted;
use AiSdk\Live\LiveClosed;
use AiSdk\Live\LiveError;
use AiSdk\Live\LiveEvent;
use AiSdk\Live\LiveOperation;
use AiSdk\Live\LiveRequest;
use AiSdk\Live\ProviderEvent;
use AiSdk\Live\ResponseCompleted;
use AiSdk\Live\TextDelta;
use AiSdk\Live\ToolCallEvent;
use AiSdk\Live\TranscriptDelta;
use AiSdk\Live\TranscriptSource;
use AiSdk\Live\TransportFrame;
use AiSdk\Live\TransportFrameType;
use AiSdk\Live\UsageEvent;
use AiSdk\Live\WebSocketEndpoint;
use AiSdk\Support\Json;

/** Gemini Live API WebSocket codec. */
final class GoogleLiveSessionDriver implements LiveSessionDriverInterface
{
    private const string ENDPOINT = 'wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContent';

    private readonly TransportConnectionInterface $connection;

    /** @var array<string, string> */
    private array $toolNames = [];

    /** @var array<string, string> */
    private array $toolCallGroups = [];

    /** @var array<string, array{order: list<string>, remaining: array<string, true>, responses: array<string, array<string, mixed>>}> */
    private array $toolGroups = [];

    /** @var list<array<string, mixed>> */
    private array $pendingPayloads = [];

    private int $toolGroupSequence = 0;

    private bool $activityStarted = false;

    public function __construct(
        private readonly string $modelId,
        private readonly GoogleOptions $options,
        private readonly LiveRequest $request,
        TransportInterface $transport,
    ) {
        $setup = GoogleLiveConfiguration::setup($modelId, $request);
        $this->validateAudioFormats();
        $endpoint = new WebSocketEndpoint(
            self::ENDPOINT.'?key='.rawurlencode($options->apiKey),
            $options->headers,
        );

        if (! $transport->supports($endpoint)) {
            throw new InvalidArgumentException(
                'The selected transport does not support Gemini Live WebSocket endpoints.',
                ['provider' => GoogleOptions::PROVIDER_NAME],
            );
        }

        $this->connection = $transport->connect($endpoint);
        $this->sendJson(['setup' => $setup]);
        $this->awaitSetupComplete();
    }

    public function sendAudio(string $bytes): void
    {
        if ($this->usesManualActivityDetection() && ! $this->activityStarted) {
            $this->sendJson(['realtimeInput' => ['activityStart' => new \stdClass]]);
            $this->activityStarted = true;
        }

        $this->sendJson([
            'realtimeInput' => [
                'audio' => [
                    'data' => base64_encode($bytes),
                    'mimeType' => $this->inputAudioMimeType(),
                ],
            ],
        ]);
    }

    public function sendText(string $text): void
    {
        if ($this->request->operation !== LiveOperation::Voice) {
            throw new InvalidArgumentException('Gemini Live Translation accepts audio input only.');
        }

        $this->sendJson(['realtimeInput' => ['text' => $text]]);
    }

    public function commitAudio(): void
    {
        if ($this->usesManualActivityDetection()) {
            if (! $this->activityStarted) {
                throw new InvalidArgumentException('Gemini Live cannot end manual activity before any activity has started.');
            }

            $this->sendJson(['realtimeInput' => ['activityEnd' => new \stdClass]]);
            $this->activityStarted = false;

            return;
        }

        $this->sendJson(['realtimeInput' => ['audioStreamEnd' => true]]);
    }

    public function clearAudio(): void
    {
        throw new InvalidArgumentException('Gemini Live streams audio directly and does not expose an input buffer to clear.');
    }

    public function requestResponse(): void
    {
        throw new InvalidArgumentException('Gemini Live starts responses from realtime input and has no response.create event.');
    }

    public function cancelResponse(): void
    {
        throw new InvalidArgumentException('Gemini Live interruption is driven by new user activity and has no standalone response.cancel event.');
    }

    public function sendToolResult(string $callId, mixed $result): void
    {
        if ($this->request->operation !== LiveOperation::Voice) {
            throw new InvalidArgumentException('Gemini Live Translation does not support tools.');
        }

        /** @var array<string, mixed> $response */
        $response = [
            'id' => $callId,
            'response' => ['result' => $result],
        ];
        if (isset($this->toolNames[$callId])) {
            $response['name'] = $this->toolNames[$callId];
        }

        $groupId = $this->toolCallGroups[$callId] ?? null;
        if ($groupId === null || ! isset($this->toolGroups[$groupId])) {
            $this->sendJson(['toolResponse' => ['functionResponses' => [$response]]]);

            return;
        }

        $this->toolGroups[$groupId]['responses'][$callId] = $response;
        unset($this->toolGroups[$groupId]['remaining'][$callId]);

        if ($this->toolGroups[$groupId]['remaining'] !== []) {
            return;
        }

        $responses = [];
        foreach ($this->toolGroups[$groupId]['order'] as $id) {
            if (isset($this->toolGroups[$groupId]['responses'][$id])) {
                $responses[] = $this->toolGroups[$groupId]['responses'][$id];
            }

            unset($this->toolCallGroups[$id]);
        }

        unset($this->toolGroups[$groupId]);
        $this->sendJson(['toolResponse' => ['functionResponses' => $responses]]);
    }

    public function events(): iterable
    {
        foreach ($this->pendingPayloads as $payload) {
            yield from $this->decode($payload);
        }
        $this->pendingPayloads = [];

        while (! $this->connection->isClosed()) {
            $frame = $this->connection->receive();
            if ($frame === null) {
                yield new LiveClosed;

                break;
            }

            if ($frame->type !== TransportFrameType::Text) {
                yield new ProviderEvent('transport.binary', ['bytes' => base64_encode($frame->payload)]);

                continue;
            }

            yield from $this->decode(Json::decode($frame->payload, 'google live event'));
        }
    }

    public function close(): void
    {
        if (! $this->connection->isClosed()) {
            $this->connection->close();
        }
    }

    private function inputAudioMimeType(): string
    {
        $format = $this->request->options['input_audio_format'] ?? null;
        if (! is_string($format) || $format === '' || in_array(strtolower($format), ['pcm', 'pcm16', 'audio/pcm'], true)) {
            return 'audio/pcm;rate=16000';
        }

        return strtolower($format);
    }

    private function validateAudioFormats(): void
    {
        $input = $this->request->options['input_audio_format'] ?? null;
        if (is_string($input) && $input !== '' && ! preg_match('#^audio/pcm;rate=[1-9][0-9]*$#', strtolower($input)) && ! in_array(strtolower($input), ['pcm', 'pcm16', 'audio/pcm'], true)) {
            throw new InvalidArgumentException(
                'Gemini Live requires raw little-endian 16-bit mono PCM input.',
                ['inputAudioFormat' => $input],
            );
        }

        $output = $this->request->options['output_audio_format'] ?? null;
        if (is_string($output) && $output !== '' && ! in_array(strtolower($output), ['pcm', 'pcm16', 'audio/pcm', 'audio/pcm;rate=24000'], true)) {
            throw new InvalidArgumentException(
                'Gemini Live produces raw 16-bit mono PCM output at 24 kHz.',
                ['outputAudioFormat' => $output],
            );
        }
    }

    private function awaitSetupComplete(): void
    {
        while (true) {
            $frame = $this->connection->receive();
            if ($frame === null) {
                throw InvalidResponseException::forProvider(
                    GoogleOptions::PROVIDER_NAME,
                    'Gemini Live closed before acknowledging the session setup.',
                );
            }

            if ($frame->type !== TransportFrameType::Text) {
                throw InvalidResponseException::forProvider(
                    GoogleOptions::PROVIDER_NAME,
                    'Gemini Live returned a binary frame before setupComplete.',
                );
            }

            $payload = Json::decode($frame->payload, 'google live setup response');
            if (array_key_exists('setupComplete', $payload)) {
                return;
            }

            $error = $payload['error'] ?? null;
            if (is_array($error)) {
                throw InvalidResponseException::forProvider(
                    GoogleOptions::PROVIDER_NAME,
                    is_string($error['message'] ?? null) ? $error['message'] : 'Gemini Live rejected the session setup.',
                    ['error' => $error],
                );
            }

            $this->pendingPayloads[] = $payload;
        }
    }

    private function usesManualActivityDetection(): bool
    {
        if (! array_key_exists('turn_detection', $this->request->options)) {
            return false;
        }

        $turnDetection = $this->request->options['turn_detection'] ?? null;

        if ($turnDetection === null) {
            return true;
        }

        if (is_string($turnDetection)) {
            return in_array(strtolower($turnDetection), ['none', 'disabled'], true);
        }

        return is_array($turnDetection) && ($turnDetection['disabled'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return iterable<LiveEvent>
     */
    private function decode(array $payload): iterable
    {
        $serverContent = $payload['serverContent'] ?? null;
        if (is_array($serverContent)) {
            foreach (['inputTranscription', 'outputTranscription'] as $key) {
                $transcription = $serverContent[$key] ?? null;
                if (is_array($transcription) && is_string($transcription['text'] ?? null) && $transcription['text'] !== '') {
                    yield new TranscriptDelta(
                        $transcription['text'],
                        source: $key === 'inputTranscription'
                            ? TranscriptSource::Input
                            : TranscriptSource::Output,
                    );
                }
            }

            $parts = is_array($serverContent['modelTurn']['parts'] ?? null)
                ? $serverContent['modelTurn']['parts']
                : [];
            foreach ($parts as $part) {
                if (! is_array($part)) {
                    continue;
                }

                if (is_string($part['text'] ?? null) && $part['text'] !== '') {
                    yield new TextDelta($part['text']);
                }

                $data = $part['inlineData']['data'] ?? null;
                if (is_string($data) && $data !== '') {
                    $bytes = base64_decode($data, true);
                    if ($bytes !== false) {
                        yield new AudioDelta($bytes);
                    }
                }
            }

            if (($serverContent['interrupted'] ?? false) === true) {
                yield new Interrupted;
            }

            if (($serverContent['turnComplete'] ?? false) === true) {
                yield new ResponseCompleted;
            }
        }

        $toolCall = $payload['toolCall'] ?? null;
        if (is_array($toolCall) && is_array($toolCall['functionCalls'] ?? null)) {
            $normalizedCalls = [];
            foreach ($toolCall['functionCalls'] as $call) {
                if (! is_array($call)) {
                    continue;
                }

                $id = is_string($call['id'] ?? null) ? $call['id'] : '';
                $name = is_string($call['name'] ?? null) ? $call['name'] : '';
                $arguments = is_array($call['args'] ?? null) ? $call['args'] : [];
                $callId = $id !== '' ? $id : $name;
                if ($callId === '') {
                    continue;
                }

                $this->toolNames[$callId] = $name;
                $normalizedCalls[] = [$callId, $name, $arguments];
            }

            if ($normalizedCalls !== []) {
                $groupId = 'tool-group-'.(++$this->toolGroupSequence);
                $order = array_map(static fn (array $call): string => $call[0], $normalizedCalls);
                $this->toolGroups[$groupId] = [
                    'order' => $order,
                    'remaining' => array_fill_keys($order, true),
                    'responses' => [],
                ];

                foreach ($normalizedCalls as [$callId, $name, $arguments]) {
                    $this->toolCallGroups[$callId] = $groupId;
                    yield new ToolCallEvent($callId, $name, $arguments);
                }
            }
        }

        $usage = $payload['usageMetadata'] ?? null;
        if (is_array($usage)) {
            $numeric = array_filter($usage, static fn (mixed $value): bool => is_int($value) || is_float($value));
            if ($numeric !== []) {
                yield new UsageEvent($numeric);
            }
        }

        $error = $payload['error'] ?? null;
        if (is_array($error)) {
            $message = is_string($error['message'] ?? null) ? $error['message'] : 'Gemini Live returned an error.';
            $code = isset($error['code']) ? (string) $error['code'] : null;
            yield new LiveError($message, $code, $error);
        }

        if ($serverContent === null && $toolCall === null && $usage === null && $error === null) {
            $type = (string) array_key_first($payload);
            yield new ProviderEvent($type !== '' ? $type : 'unknown', $payload);
        }
    }

    /** @param array<string, mixed> $payload */
    private function sendJson(array $payload): void
    {
        $this->connection->send(TransportFrame::text(Json::encode($payload)));
    }
}
