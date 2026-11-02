<?php

declare(strict_types=1);

namespace App\Application\Lab;

interface LabRepository
{
    /** @param array<string, mixed> $proof */
    public function saveSubmission(string $studentId, string $challenge, array $proof): void;

    /** @return list<string> */
    public function completedChallenges(): array;

    /** @return list<string> */
    public function completedChallengesForStudent(string $studentId): array;

    /** @return list<array<string, mixed>> */
    public function recentEvents(int $limit = 20): array;

    /** @return list<array<string, mixed>> */
    public function studentOverview(): array;

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $response
     */
    public function saveRequest(string $studentId, array $request, array $response): void;

    /** @return list<array<string, mixed>> */
    public function requestHistory(string $studentId, int $limit = 50): array;

    public function reset(): void;
}
