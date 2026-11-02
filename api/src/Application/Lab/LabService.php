<?php

declare(strict_types=1);

namespace App\Application\Lab;

use App\Domain\Lab\ChallengeCatalog;
use App\Domain\Lab\ExerciseEngine;
use App\Domain\Lab\ExerciseRequest;
use App\Domain\Lab\InvestigationProgress;
use InvalidArgumentException;

final readonly class LabService
{
    public function __construct(
        private LabRepository $repository,
        private ChallengeCatalog $catalog = new ChallengeCatalog(),
        private ExerciseEngine $engine = new ExerciseEngine(),
        private InvestigationProgress $investigation = new InvestigationProgress(),
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function challenges(?string $studentId = null): array
    {
        $completed = $studentId === null
            ? $this->repository->completedChallenges()
            : $this->repository->completedChallengesForStudent($studentId);
        $history = $studentId === null ? [] : $this->repository->requestHistory($studentId, 0);

        return array_map(
            fn ($challenge): array => $challenge->toArray() + $this->stageData($challenge->slug, $history, in_array($challenge->slug, $completed, true)) + [
                'status' => in_array($challenge->slug, $completed, true) ? 'completed' : 'pending',
            ],
            $this->catalog->all(),
        );
    }

    /** @param array<string, mixed> $proof */
    public function complete(string $studentId, string $challenge, array $proof = []): void
    {
        $this->catalog->find($challenge);
        $this->repository->saveSubmission($studentId, $challenge, $proof);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function sendRequest(string $studentId, array $input): array
    {
        $method = strtoupper(trim((string) ($input['method'] ?? 'GET')));
        $path = trim((string) ($input['path'] ?? ''));
        $headers = is_array($input['headers'] ?? null) ? $input['headers'] : [];
        $body = is_array($input['body'] ?? null) || is_string($input['body'] ?? null) ? $input['body'] : [];
        $mode = (string) ($input['mode'] ?? 'single');
        if (!in_array($method, ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'], true) || $path === '' || strlen($path) > 500) {
            throw new InvalidArgumentException('Проверьте метод и виртуальный путь запроса.');
        }
        if (!in_array($mode, ['single', 'parallel'], true)) {
            throw new InvalidArgumentException('Неизвестный режим отправки.');
        }

        /** @var array<string, string> $stringHeaders */
        $stringHeaders = array_map('strval', $headers);
        $request = new ExerciseRequest($method, $path, $stringHeaders, $body, $mode);
        $history = $this->repository->requestHistory($studentId, 0);
        $result = $this->engine->handle($request, $history);
        $response = $result->toArray();
        $candidateHistory = [...$history, $request->toArray() + ['response' => $response]];
        $response['earned_challenges'] = array_values(array_filter($result->earnedChallenges, function (string $slug) use ($candidateHistory): bool {
            $stages = $this->investigation->forChallenge($slug, $candidateHistory);
            return count(array_filter($stages, static fn (array $stage): bool => $stage['status'] === 'completed')) === count($stages);
        }));
        $this->repository->saveRequest($studentId, $request->toArray(), $response);
        foreach ($response['earned_challenges'] as $challenge) {
            $this->complete($studentId, $challenge, ['request' => $request->toArray(), 'status' => $result->status]);
        }
        return $response;
    }

    /** @return list<array<string, mixed>> */
    public function requestHistory(string $studentId): array
    {
        return $this->repository->requestHistory($studentId, 0);
    }

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $students = array_map(function (array $student): array {
            $completed = is_array($student['completed_challenges'] ?? null)
                ? $student['completed_challenges']
                : [];

            $history = $this->repository->requestHistory((string) $student['id'], 0);
            $stages = [];
            foreach ($this->catalog->all() as $challenge) {
                $data = $this->stageData($challenge->slug, $history, in_array($challenge->slug, $completed, true));
                $stages[$challenge->slug] = $data['stage_progress'] + ['stages' => $data['stages']];
            }
            return $student + ['progress' => count($completed), 'challenge_stages' => $stages];
        }, $this->repository->studentOverview());
        $completed = array_sum(array_map(
            static fn (array $student): int => (int) $student['progress'],
            $students,
        ));

        return [
            'stats' => [
                'students' => count($students),
                'completed' => $completed,
                'available' => count($students) * count($this->catalog->all()),
            ],
            'students' => $students,
            'challenges' => array_map(static fn ($challenge): array => $challenge->toArray(), $this->catalog->all()),
            'events' => $this->repository->recentEvents(),
        ];
    }

    public function reset(): void
    {
        $this->repository->reset();
    }

    /**
     * @param list<array<string,mixed>> $history
     * @return array{stages:list<array<string,mixed>>,stage_progress:array{completed:int,total:int}}
     */
    private function stageData(string $slug, array $history, bool $completed): array
    {
        $stages = $this->investigation->forChallenge($slug, $history, $completed);
        return ['stages' => $stages, 'stage_progress' => [
            'completed' => count(array_filter($stages, static fn (array $stage): bool => $stage['status'] === 'completed')),
            'total' => count($stages),
        ]];
    }
}
