<?php

namespace App\Contracts;

interface MisdRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findStudent(string $studentNumber): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findFaculty(string $employeeNumber): ?array;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function allStudents(): array;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function allFaculty(): array;
}
