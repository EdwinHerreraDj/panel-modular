<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property bool $must_change_password
 * @property string|null $avatar_path
 * @property string $locale
 * @property UserStatus $status
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property Carbon|null $password_changed_at
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $deleted_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_path',
        'locale',
        'status',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'status' => UserStatus::class,
        ];
    }

    /**
     * @return HasMany<AuthLog, $this>
     */
    public function authLogs(): HasMany
    {
        return $this->hasMany(AuthLog::class);
    }

    /**
     * Solo las cuentas activas pueden autenticarse. Se comprueba en el
     * controlador de acceso y en cada petición mediante middleware.
     */
    public function canAuthenticate(): bool
    {
        return $this->status->canAuthenticate();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleName::SuperAdmin->value);
    }

    /**
     * Rol del usuario. Cada cuenta tiene exactamente uno.
     */
    public function roleName(): ?RoleName
    {
        $name = $this->getRoleNames()->first();

        return is_string($name) ? RoleName::tryFrom($name) : null;
    }

    /**
     * Sustituye el rol actual por el indicado.
     */
    public function assignSingleRole(RoleName $role): void
    {
        $this->syncRoles([$role->value]);
    }

    /**
     * Iniciales para el avatar de la barra superior cuando no hay imagen.
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }

    /**
     * Registra el acceso correcto sin disparar eventos ni tocar `updated_at`.
     */
    public function recordSuccessfulLogin(?string $ip): void
    {
        $this->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ])->saveQuietly();
    }

    /**
     * Búsqueda por nombre o correo electrónico.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('name', 'like', $like)->orWhere('email', 'like', $like);
        });
    }
}
