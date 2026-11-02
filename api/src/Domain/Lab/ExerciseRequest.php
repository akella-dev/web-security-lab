<?php

declare(strict_types=1);

namespace App\Domain\Lab;

final readonly class ExerciseRequest
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|string $body
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $headers = [],
        public array|string $body = [],
        public string $mode = 'single',
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['method' => $this->method, 'path' => $this->path, 'headers' => $this->headers, 'body' => $this->body, 'mode' => $this->mode];
    }
}
