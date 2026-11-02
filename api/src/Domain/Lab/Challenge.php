<?php

declare(strict_types=1);

namespace App\Domain\Lab;

final readonly class Challenge
{
    /**
     * @param list<string> $learningGoals
     */
    public function __construct(
        public int $number,
        public string $slug,
        public string $title,
        public string $module,
        public string $difficulty,
        public int $estimatedMinutes,
        public string $category,
        public string $summary,
        public string $mission,
        public string $safetyBoundary,
        public array $learningGoals,
        /** @var list<string> */
        public array $hints,
        /** @var array{method:string,path:string,headers:array<string,string>,body:array<string,mixed>|string,mode:string} */
        public array $starterRequest,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $starterRequest = $this->starterRequest;
        if ($starterRequest['headers'] === []) {
            $starterRequest['headers'] = (object) [];
        }
        if ($starterRequest['body'] === []) {
            $starterRequest['body'] = (object) [];
        }

        return [
            'number' => $this->number,
            'slug' => $this->slug,
            'title' => $this->title,
            'module' => $this->module,
            'difficulty' => $this->difficulty,
            'estimated_minutes' => $this->estimatedMinutes,
            'category' => $this->category,
            'summary' => $this->summary,
            'mission' => $this->mission,
            'safety_boundary' => $this->safetyBoundary,
            'learning_goals' => $this->learningGoals,
            'hints' => $this->hints,
            'starter_request' => $starterRequest,
        ];
    }
}
