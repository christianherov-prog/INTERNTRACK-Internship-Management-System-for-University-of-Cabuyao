<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'student_number' => null,
            'faculty_number' => null,
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'notification_preferences' => null,
        ];
    }

    public function role(string $role): static
    {
        return $this->state(function (array $attributes) use ($role) {
            $isStudent = $role === 'student';
            return [
                'role' => $role,
                'student_number' => $isStudent ? (string) fake()->unique()->numberBetween(1000000, 9999999) : null,
                'faculty_number' => !$isStudent ? strtoupper(fake()->unique()->bothify('FAC-####')) : null,
            ];
        });
    }

    public function student(): static
    {
        return $this->role('student');
    }

    public function coordinator(): static
    {
        return $this->role('coordinator');
    }

    public function supervisor(): static
    {
        return $this->role('supervisor');
    }

    public function faculty(): static
    {
        return $this->role('faculty');
    }

    public function director(): static
    {
        return $this->role('director');
    }
}
