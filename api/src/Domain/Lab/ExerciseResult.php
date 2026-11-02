<?php

declare(strict_types=1);

namespace App\Domain\Lab;

final readonly class ExerciseResult
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|string $body
     * @param list<string> $earnedChallenges
     */
    public function __construct(
        public int $status,
        public array $headers,
        public array|string $body,
        public array $earnedChallenges = [],
        public string $preview = 'json',
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['status' => $this->status, 'headers' => $this->headers, 'body' => $this->body, 'earned_challenges' => $this->earnedChallenges, 'preview' => $this->preview];
    }
}
