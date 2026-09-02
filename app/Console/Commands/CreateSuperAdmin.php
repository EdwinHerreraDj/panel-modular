<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\PasswordRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Alta de un superadministrador sin dejar la contraseña escrita en ningún
 * archivo de configuración.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'panel:crear-superadmin
                            {--email= : Correo electrónico de la cuenta}
                            {--name= : Nombre completo}';

    protected $description = 'Crea una cuenta de superadministrador de la plataforma';

    public function handle(): int
    {
        $name = $this->option('name') ?? text(
            label: 'Nombre completo',
            required: 'El nombre es obligatorio.',
        );

        $email = $this->option('email') ?? text(
            label: 'Correo electrónico',
            required: 'El correo electrónico es obligatorio.',
        );

        $email = mb_strtolower(trim((string) $email));

        $plainPassword = password(
            label: 'Contraseña',
            required: 'La contraseña es obligatoria.',
        );

        $confirmation = password(
            label: 'Repite la contraseña',
            required: 'Debes repetir la contraseña.',
        );

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $plainPassword,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:filter', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', PasswordRules::default()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => $plainPassword,
            'status' => UserStatus::Active,
            'locale' => 'es',
            'email_verified_at' => now(),
            'password_changed_at' => now(),
            'must_change_password' => false,
        ])->save();

        Role::findOrCreate(RoleName::SuperAdmin->value, 'web');
        $user->assignSingleRole(RoleName::SuperAdmin);

        $this->info("Superadministrador creado correctamente: {$email}");

        return self::SUCCESS;
    }
}
