<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\Student\StudentRepository;
use App\Application\Student\StudentService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class StudentServiceTest extends TestCase
{
    public function test_it_enrolls_and_restores_a_student_session(): void
    {
        $repository = new InMemoryStudentRepository();
        $service = new StudentService($repository);

        $enrollment = $service->enroll('  Иван Петров  ', '  ИВТ-21 ');
        $student = $service->authenticate($enrollment['session_token']);

        self::assertSame('Иван Петров', $student['name']);
        self::assertSame('ИВТ-21', $student['group']);
        self::assertSame($enrollment['student']['id'], $student['id']);
        self::assertNotSame('', $enrollment['session_token']);
    }

    public function test_it_rejects_an_incomplete_student_identity(): void
    {
        $service = new StudentService(new InMemoryStudentRepository());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('имя и группу');

        $service->enroll('', 'ИВТ-21');
    }

    public function test_it_rejects_an_unknown_session(): void
    {
        $service = new StudentService(new InMemoryStudentRepository());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(401);

        $service->authenticate('unknown');
    }
}

final class InMemoryStudentRepository implements StudentRepository
{
    /** @var array<string, array{id:string,name:string,group:string}> */
    private array $bySession = [];

    public function createStudent(string $id, string $name, string $group, string $sessionToken): array
    {
        $student = ['id' => $id, 'name' => $name, 'group' => $group];
        $this->bySession[$sessionToken] = $student;

        return $student;
    }

    public function findStudentBySession(string $sessionToken): ?array
    {
        return $this->bySession[$sessionToken] ?? null;
    }

    public function touchStudent(string $studentId): void
    {
    }
}
