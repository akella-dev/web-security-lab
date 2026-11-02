<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\Lab\LabRepository;
use App\Application\Lab\LabService;
use PHPUnit\Framework\TestCase;

final class LabServiceTest extends TestCase
{
    public function test_partial_stages_are_persistent_isolated_and_visible_to_the_teacher(): void
    {
        $repository = new InMemoryLabRepository();
        $service = new LabService($repository);
        $service->sendRequest('student-1', ['method' => 'GET', 'path' => '/api/support/tickets/42']);
        $service->complete('student-1', 'price-tampering');
        $first = $service->challenges('student-1')[3];
        self::assertSame(['completed' => 1, 'total' => 2], $first['stage_progress'] ?? null);
        self::assertSame('pending', $first['status']);
        self::assertSame(['completed' => 0, 'total' => 2], $service->challenges('student-2')[3]['stage_progress']);
        self::assertEquals($first['stages'], (new LabService($repository))->challenges('student-1')[3]['stages']);
        self::assertSame(1, $service->overview()['students'][0]['challenge_stages']['idor-chain']['completed']);
    }

    public function test_new_ssrf_completion_requires_all_stages_in_order(): void
    {
        $service = new LabService(new InMemoryLabRepository());
        $send = static fn (string $url): array => $service->sendRequest('student-1', ['method' => 'POST', 'path' => '/api/tools/preview', 'body' => ['url' => $url]]);
        $send('http://metadata.internal/latest/');
        self::assertSame([], $send('http://metadata.internal/latest/secret')['earned_challenges']);
        $send('https://docs.example.test/help');
        $send('http://metadata.internal/latest/');
        self::assertSame(['ssrf-preview'], $send('http://metadata.internal/latest/secret')['earned_challenges']);
    }

    public function test_completing_the_same_mission_is_idempotent(): void
    {
        $repository = new InMemoryLabRepository();
        $service = new LabService($repository);

        $service->complete('student-1', 'price-tampering', ['charged' => 1]);
        $service->complete('student-1', 'price-tampering', ['charged' => 1]);

        self::assertCount(1, $repository->submissions);
    }

    public function test_overview_reports_progress_per_student(): void
    {
        $repository = new InMemoryLabRepository();
        $service = new LabService($repository);
        $service->complete('student-1', 'price-tampering', ['charged' => 1]);

        $overview = $service->overview();

        self::assertSame(1, $overview['stats']['students']);
        self::assertSame(1, $overview['stats']['completed']);
        self::assertSame(16, $overview['stats']['available']);
        self::assertSame('student-1', $overview['students'][0]['id']);
        self::assertSame(['price-tampering'], $overview['students'][0]['completed_challenges']);
    }

    public function test_progress_is_isolated_for_each_student(): void
    {
        $repository = new InMemoryLabRepository();
        $service = new LabService($repository);
        $service->complete('student-1', 'price-tampering', ['charged' => 1]);

        $firstStudent = $service->challenges('student-1');
        $secondStudent = $service->challenges('student-2');

        self::assertSame('completed', $firstStudent[0]['status']);
        self::assertSame('pending', $secondStudent[0]['status']);
    }

    public function test_workbench_records_request_and_completes_observed_outcome(): void
    {
        $repository = new InMemoryLabRepository();
        $service = new LabService($repository);

        $result = $service->sendRequest('student-1', [
            'method' => 'POST',
            'path' => '/api/store/orders',
            'headers' => [],
            'body' => ['product_id' => 'honey-set', 'quantity' => 1, 'unit_price' => 1],
            'mode' => 'single',
        ]);

        self::assertSame(201, $result['status']);
        self::assertContains('price-tampering', $result['earned_challenges']);
        self::assertSame(['price-tampering'], $repository->completedChallengesForStudent('student-1'));
        self::assertCount(1, $service->requestHistory('student-1'));
        self::assertCount(0, $service->requestHistory('student-2'));
    }

    public function test_workbench_uses_prior_student_requests_for_a_chain(): void
    {
        $repository = new InMemoryLabRepository();
        $service = new LabService($repository);

        $service->sendRequest('student-1', ['method' => 'GET', 'path' => '/api/support/tickets/42']);
        $result = $service->sendRequest('student-1', ['method' => 'GET', 'path' => '/api/store/invoices/ORD-b7f3']);

        self::assertContains('idor-chain', $result['earned_challenges']);
    }

    public function test_overview_uses_the_full_sixteen_task_course(): void
    {
        $repository = new InMemoryLabRepository();
        $service = new LabService($repository);
        $service->complete('student-1', 'price-tampering');

        self::assertSame(16, $service->overview()['stats']['available']);
    }

    public function test_saved_state_survives_more_than_fifty_requests_and_remains_private(): void
    {
        $service = new LabService(new InMemoryLabRepository());
        $service->sendRequest('student-1', ['method' => 'POST', 'path' => '/api/comments', 'body' => ['content' => '<svg onload=alert(1)>']]);
        for ($index = 0; $index < 55; $index++) {
            $service->sendRequest('student-1', ['method' => 'GET', 'path' => '/api/store/shipments']);
        }
        self::assertCount(56, $service->requestHistory('student-1'));
        $read = ['method' => 'GET', 'path' => '/api/comments'];
        self::assertContains('stored-xss', $service->sendRequest('student-1', $read)['earned_challenges']);
        self::assertSame([], $service->sendRequest('student-2', $read)['earned_challenges']);
    }
}

final class InMemoryLabRepository implements LabRepository
{
    /** @var array<string, array{student_id: string, challenge: string, proof: array<string, mixed>}> */
    public array $submissions = [];

    /** @var array<string, list<array<string, mixed>>> */
    public array $requests = [];

    public function saveSubmission(string $studentId, string $challenge, array $proof): void
    {
        $this->submissions[$studentId . ':' . $challenge] = [
            'student_id' => $studentId,
            'challenge' => $challenge,
            'proof' => $proof,
        ];
    }

    public function completedChallenges(): array
    {
        return array_values(array_unique(array_column($this->submissions, 'challenge')));
    }

    public function completedChallengesForStudent(string $studentId): array
    {
        return array_values(array_map(
            static fn (array $submission): string => $submission['challenge'],
            array_filter(
                $this->submissions,
                static fn (array $submission): bool => $submission['student_id'] === $studentId,
            ),
        ));
    }

    public function recentEvents(int $limit = 20): array
    {
        return [];
    }

    public function studentOverview(): array
    {
        $ids = array_values(array_unique(array_map(
            static fn (array $submission): string => $submission['student_id'],
            $this->submissions,
        )));

        return array_map(fn (string $id): array => [
            'id' => $id,
            'name' => 'Иван Петров',
            'group' => 'ИВТ-21',
            'completed_challenges' => $this->completedChallengesForStudent($id),
            'last_seen_at' => '2026-09-26 10:00:00',
        ], $ids);
    }

    public function saveRequest(string $studentId, array $request, array $response): void
    {
        $this->requests[$studentId][] = $request + ['response' => $response, 'created_at' => '2026-09-26 10:00:00'];
    }

    public function requestHistory(string $studentId, int $limit = 50): array
    {
        return array_slice($this->requests[$studentId] ?? [], -$limit);
    }

    public function reset(): void
    {
        $this->submissions = [];
    }
}
