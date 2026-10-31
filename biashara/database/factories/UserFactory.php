<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => 'u_'.Str::lower(Str::random(8)),
            'email' => null,
            'password' => 'password123',
            'role_id' => fn () => Role::query()->where('slug', Role::CASHIER)->value('id'),
            'is_active' => true,
        ];
    }

    public function role(string $slug): static
    {
        return $this->state(fn () => ['role_id' => Role::query()->where('slug', $slug)->value('id')]);
    }
}
