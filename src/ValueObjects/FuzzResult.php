<?php

declare(strict_types=1);

namespace Fuzz\ValueObjects;

final class FuzzResult
{
    public function __construct(
        public readonly bool $crashed,
        public readonly int $runs,
        public readonly int $features,
        public readonly int $librarySize,
        public readonly ?string $payload = null,
        public readonly ?string $exception = null,
        public readonly ?string $crashPath = null,
        public readonly string $message = '',
    ) {}

    public function crashed(): bool
    {
        return $this->crashed;
    }

    public function ok(): bool
    {
        return ! $this->crashed;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'result',
            'crashed' => $this->crashed,
            'runs' => $this->runs,
            'features' => $this->features,
            'librarySize' => $this->librarySize,
            'payload' => $this->payload !== null ? base64_encode($this->payload) : null,
            'exception' => $this->exception,
            'crashPath' => $this->crashPath,
            'message' => $this->message,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $payload = null;
        if (isset($data['payload']) && is_string($data['payload']) && $data['payload'] !== '') {
            $decoded = base64_decode($data['payload'], true);
            $payload = $decoded === false ? null : $decoded;
        }

        return new self(
            crashed: is_bool($data['crashed'] ?? null) ? $data['crashed'] : false,
            runs: is_int($data['runs'] ?? null) ? $data['runs'] : 0,
            features: is_int($data['features'] ?? null) ? $data['features'] : 0,
            librarySize: is_int($data['librarySize'] ?? null) ? $data['librarySize'] : 0,
            payload: $payload,
            exception: isset($data['exception']) && is_string($data['exception']) ? $data['exception'] : null,
            crashPath: isset($data['crashPath']) && is_string($data['crashPath']) ? $data['crashPath'] : null,
            message: isset($data['message']) && is_string($data['message']) ? $data['message'] : '',
        );
    }

    public static function success(int $runs, int $features, int $librarySize): self
    {
        return new self(
            crashed: false,
            runs: $runs,
            features: $features,
            librarySize: $librarySize,
            message: "Fuzz completed {$runs} runs without crashes (features: {$features}, library: {$librarySize}).",
        );
    }
}
