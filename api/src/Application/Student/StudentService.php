<?php

declare(strict_types=1);

namespace App\Application\Student;

use InvalidArgumentException;

final readonly class StudentService
{
    public function __construct(private StudentRepository $repository)
    {
    }

    /** @return array{student:array{id:string,name:string,group:string},session_token:string} */
    public function enroll(string $name, string $group): array
    {
        $name = trim($name);
        $group = trim($group);
        if ($name === '' || $group === '') {
            throw new InvalidArgumentException('Укажите имя и группу.');
        }
        if (strlen($name) > 120 || strlen($group) > 40) {
            throw new InvalidArgumentException('Имя или группа слишком длинные.');
        }

        $id = bin2hex(random_bytes(16));
        $sessionToken = bin2hex(random_bytes(32));
        $student = $this->repository->createStudent($id, $name, $group, $sessionToken);

        return ['student' => $student, 'session_token' => $sessionToken];
    }

    /** @return array{id:string,name:string,group:string} */
    public function authenticate(string $sessionToken): array
    {
        if ($sessionToken === '') {
            throw new InvalidArgumentException('Требуется вход в учебный стенд.', 401);
        }

        $student = $this->repository->findStudentBySession($sessionToken);
        if ($student === null) {
            throw new InvalidArgumentException('Сессия не найдена. Войдите заново.', 401);
        }

        $this->repository->touchStudent($student['id']);

        return $student;
    }
}
