<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Crea (o restablece) la cuenta de superadministrador de desarrollo a partir de
 * las variables SEED_SUPER_ADMIN_* del entorno.
 *
 * No se ejecuta en producción: allí se usa `php artisan panel:crear-superadmin`,
 * que pide la contraseña de forma interactiva y no la deja escrita en disco.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error(
                'Este seeder no se ejecuta en producción. Usa «php artisan panel:crear-superadmin».',
            );

            return;
        }

        /** @var array{name: string, email: string, password: string|null} $config */
        $config = config('security.super_admin');

        $password = $config['password'];

        if ($password === null || $password === '') {
            $this->command->warn(
                'No se ha creado el superadministrador: define SEED_SUPER_ADMIN_PASSWORD en .env '
                .'o ejecuta «php artisan panel:crear-superadmin».',
            );

            return;
        }

        /** @var User|null $user */
        $user = User::withTrashed()->where('email', $config['email'])->first();
        $existed = $user !== null;

        $user ??= new User;

        $user->forceFill([
            'name' => $config['name'],
            'email' => $config['email'],
            'password' => $password,
            'status' => UserStatus::Active,
            'locale' => 'es',
            'email_verified_at' => now(),
            'password_changed_at' => now(),
            'must_change_password' => false,
            'deleted_at' => null,
        ])->save();

        $user->assignSingleRole(RoleName::SuperAdmin);

        $this->command->info(
            $existed
                ? "Superadministrador actualizado: {$config['email']}"
                : "Superadministrador creado: {$config['email']}",
        );
    }
}
