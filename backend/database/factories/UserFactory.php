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
            'username' => strtoupper(fake()->unique()->bothify('USR-####')),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'student',
            'is_active' => true,
            'notification_preferences' => null,
        ];
    }

    public function role(string $role): static
    {
        return $this->state(fn () => ['role' => $role]);
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
