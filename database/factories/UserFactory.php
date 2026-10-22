<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use App\Support\Authorization\RoleSlug;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role_id' => null,
            'is_active' => true,
            'deactivated_at' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }

    public function withRole(RoleSlug|string|Role $role): static
    {
        return $this->state(function () use ($role) {
            if ($role instanceof Role) {
                return ['role_id' => $role->id];
            }

            $slug = $role instanceof RoleSlug ? $role->value : $role;

            $roleId = Role::query()->where('slug', $slug)->value('id');

            return ['role_id' => $roleId];
        });
    }

    public function administrator(): static
    {
        return $this->withRole(RoleSlug::Administrator);
    }
}
