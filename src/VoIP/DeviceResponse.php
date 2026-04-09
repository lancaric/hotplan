<?php

declare(strict_types=1);

namespace HotPlan\VoIP;

/**
 * Standardized response from VoIP device operations.
 */
class DeviceResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $data = null,
        public readonly ?string $error = null,
        public readonly int $httpCode = 200,
        public readonly array $metadata = [],
    ) {}

    public static function success(?string $data = null, int $httpCode = 200, array $metadata = []): self
    {
        return new self(true, $data, null, $httpCode, $metadata);
    }

    public static function error(string $error, int $httpCode = 500, ?string $data = null, array $metadata = []): self
    {
        return new self(false, $data, $error, $httpCode, $metadata);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isError(): bool
    {
        return !$this->success;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'error' => $this->error,
            'http_code' => $this->httpCode,
            'metadata' => $this->metadata,
        ];
    }
}
