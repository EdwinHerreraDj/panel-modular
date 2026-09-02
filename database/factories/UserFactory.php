<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Contraseña compartida por las pruebas para no rehacer el hash en cada usuario.
     */
    public const PASSWORD = 'Contrasena.Segura.2026#';

    protected static ?string $passwordHash = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$passwordHash ??= Hash::make(self::PASSWORD),
            'must_change_password' => false,
            'avatar_path' => null,
            'status' => UserStatus::Active,
            'locale' => 'es',
            'remember_token' => Str::random(10),
            // Se declaran explícitamente para que el modelo devuelto por la
            // factoría tenga todos los atributos y no falle Model::shouldBeStrict().
            'last_login_at' => null,
            'last_login_ip' => null,
            'password_changed_at' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => UserStatus::Suspended]);
    }

    public function invited(): static
    {
        return $this->state(fn (): array => [
            'status' => UserStatus::Invited,
            'email_verified_at' => null,
        ]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn (): array => ['must_change_password' => true]);
    }

    /**
     * Asigna un rol al usuario, creándolo si el seeder no se ha ejecutado.
     */
    public function withRole(RoleName $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            Role::findOrCreate($role->value, 'web');
            $user->assignSingleRole($role);
        });
    }

    public function superAdmin(): static
    {
        return $this->withRole(RoleName::SuperAdmin);
    }

    public function editor(): static
    {
        return $this->withRole(RoleName::Editor);
    }

    public function reviewer(): static
    {
        return $this->withRole(RoleName::Reviewer);
    }

    public function author(): static
    {
        return $this->withRole(RoleName::Author);
    }

    public function reader(): static
    {
        return $this->withRole(RoleName::Reader);
    }
}
