<?php

declare(strict_types=1);

namespace App\Application\Student;

interface StudentRepository
{
    /** @return array{id:string,name:string,group:string} */
    public function createStudent(string $id, string $name, string $group, string $sessionToken): array;

    /** @return array{id:string,name:string,group:string}|null */
    public function findStudentBySession(string $sessionToken): ?array;

    public function touchStudent(string $studentId): void;
}
