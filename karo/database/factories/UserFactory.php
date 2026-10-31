<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->name(), 'username' => 'u_'.Str::lower(Str::random(8)), 'password' => 'Secret!2026', 'is_active' => true];
    }
}
