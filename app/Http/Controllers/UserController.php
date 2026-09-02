<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RecordActivity;
use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Support\PasswordPolicy;
use App\Support\SuperAdminGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Columnas por las que se permite ordenar; la lista blanca evita inyectar
     * SQL a través del parámetro de ordenación.
     *
     * @var list<string>
     */
    private const SORTABLE = ['name', 'email', 'status', 'last_login_at', 'created_at'];

    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => $request->string('status')->toString(),
            'extra' => $request->string('extra')->toString(),
            'trashed' => $request->boolean('trashed'),
            'sort' => in_array($request->string('sort')->toString(), self::SORTABLE, true)
                ? $request->string('sort')->toString()
                : 'name',
            'direction' => $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc',
        ];

        $statuses = array_column(UserStatus::cases(), 'value');
        $roles = array_column(RoleName::cases(), 'value');

        $users = User::query()
            ->with('roles:id,name')
            ->search($filters['search'])
            ->when(
                in_array($filters['status'], $statuses, true),
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->when(
                in_array($filters['extra'], $roles, true),
                fn ($query) => $query->role($filters['extra']),
            )
            ->when($filters['trashed'], fn ($query) => $query->onlyTrashed())
            ->orderBy($filters['sort'], $filters['direction'])
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user): array => $this->toListItem($request, $user));

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $filters,
            'statusOptions' => array_map(
                fn (UserStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                UserStatus::cases(),
            ),
            'roleOptions' => RoleName::options(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Create', [
            'roleOptions' => RoleName::options(),
            'passwordPolicy' => $this->passwordPolicy(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = new User;

            $user->forceFill([
                'name' => (string) $request->string('name'),
                'email' => (string) $request->string('email'),
                'password' => (string) $request->string('password'),
                'status' => UserStatus::Active,
                'locale' => 'es',
                'email_verified_at' => now(),
                'password_changed_at' => now(),
                // La contraseña la ha elegido otra persona: hay que cambiarla
                // en el primer acceso.
                'must_change_password' => true,
            ])->save();

            $role = RoleName::from((string) $request->string('role'));
            $user->assignSingleRole($role);

            $this->recordActivity->handle($user, ActivityEvent::Created, sprintf(
                'Alta del usuario «%s» con el rol %s',
                $user->name,
                $role->label(),
            ));

            return $user;
        });

        return redirect()
            ->route('users.index')
            ->with('success', "Usuario «{$user->name}» creado. Deberá cambiar la contraseña en su primer acceso.");
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roleName()?->value,
                'status' => $user->status->value,
                'must_change_password' => $user->must_change_password,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'roleOptions' => RoleName::options(),
            'statusOptions' => array_map(
                fn (UserStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                UserStatus::cases(),
            ),
            'passwordPolicy' => $this->passwordPolicy(),
            'isSelf' => $user->is(request()->user()),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $role = RoleName::from((string) $request->string('role'));
        $status = UserStatus::from((string) $request->string('status'));

        // Degradar o suspender al último superadministrador operativo dejaría
        // la aplicación sin administración.
        if ($role !== RoleName::SuperAdmin || $status !== UserStatus::Active) {
            SuperAdminGuard::assertNotLastActive(
                $user,
                $role !== RoleName::SuperAdmin ? 'role' : 'status',
                'Es la única cuenta de superadministrador activa. Asigna ese rol a otra persona antes de cambiarlo.',
            );
        }

        DB::transaction(function () use ($request, $user, $role, $status): void {
            $user->update([
                'name' => (string) $request->string('name'),
                'email' => (string) $request->string('email'),
                'status' => $status,
            ]);

            $previousRole = $user->roleName();

            if ($previousRole !== $role) {
                $user->assignSingleRole($role);
                $user->setRelation('roles', $user->roles()->get());
            }

            if ($user->wasChanged() || $previousRole !== $role) {
                $this->recordActivity->forUpdate($user, sprintf(
                    'Modificación del usuario «%s»%s',
                    $user->name,
                    $previousRole !== $role
                        ? sprintf(' (rol: %s → %s)', $previousRole?->label() ?? '—', $role->label())
                        : '',
                ));
            }
        });

        return redirect()
            ->route('users.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    /**
     * Fija una contraseña nueva y obliga a cambiarla en el siguiente acceso.
     */
    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $user->forceFill([
                'password' => (string) $request->string('password'),
                'must_change_password' => true,
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            // Se cierran todas sus sesiones abiertas.
            DB::table('sessions')->where('user_id', $user->getKey())->delete();

            $this->recordActivity->handle(
                $user,
                ActivityEvent::Updated,
                "Contraseña restablecida por un administrador para «{$user->name}»",
            );
        });

        return redirect()
            ->route('users.index')
            ->with('success', "Contraseña de «{$user->name}» actualizada. Deberá cambiarla en su próximo acceso.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        SuperAdminGuard::assertNotLastActive(
            $user,
            'user',
            'Es la única cuenta de superadministrador activa: no se puede enviar a la papelera.',
        );

        DB::transaction(function () use ($user): void {
            $user->delete();

            DB::table('sessions')->where('user_id', $user->getKey())->delete();

            $this->recordActivity->handle(
                $user,
                ActivityEvent::Deleted,
                "Usuario «{$user->name}» enviado a la papelera",
            );
        });

        return redirect()
            ->route('users.index')
            ->with('success', 'Usuario enviado a la papelera.');
    }

    public function restore(User $user): RedirectResponse
    {
        $this->authorize('restore', $user);

        DB::transaction(function () use ($user): void {
            $user->restore();

            $this->recordActivity->handle(
                $user,
                ActivityEvent::Restored,
                "Usuario «{$user->name}» restaurado",
            );
        });

        return redirect()
            ->route('users.index')
            ->with('success', 'Usuario restaurado correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(Request $request, User $user): array
    {
        $role = $user->roleName();
        $editor = $request->user();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'initials' => $user->initials(),
            'role' => $role?->value,
            'role_label' => $role?->label() ?? 'Sin rol',
            'role_tone' => $role?->tone() ?? 'neutral',
            'status' => $user->status->value,
            'status_label' => $user->status->label(),
            'must_change_password' => $user->must_change_password,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'deleted_at' => $user->deleted_at?->toIso8601String(),
            'is_self' => $editor !== null && $editor->is($user),
            'can' => [
                'update' => $editor?->can('update', $user) ?? false,
                'delete' => $editor?->can('delete', $user) ?? false,
                'restore' => $editor?->can('restore', $user) ?? false,
                'resetPassword' => $editor?->can('resetPassword', $user) ?? false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function passwordPolicy(): array
    {
        return PasswordPolicy::forDisplay();
    }
}
