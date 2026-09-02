<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuthEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Entrada del registro de auditoría de accesos.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $email
 * @property AuthEvent $event
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string, mixed>|null $context
 * @property Carbon|null $created_at
 */
class AuthLog extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'email',
        'event',
        'ip_address',
        'user_agent',
        'context',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => AuthEvent::class,
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Registra un evento de autenticación.
     *
     * El correo se guarda siempre (aunque no exista el usuario) para poder
     * investigar intentos contra cuentas inexistentes; nunca se guarda la
     * contraseña ni ningún otro dato sensible.
     *
     * @param  array<string, mixed>  $context
     */
    public static function record(
        AuthEvent $event,
        Request $request,
        ?User $user = null,
        ?string $email = null,
        array $context = [],
    ): self {
        return self::create([
            'user_id' => $user?->getKey(),
            'email' => $email ?? $user?->email,
            'event' => $event,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'context' => $context === [] ? null : $context,
        ]);
    }
}
