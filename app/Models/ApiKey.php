<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ApiKeyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Clave de acceso a la API pública.
 *
 * La clave en claro solo existe en el momento de crearla: después se guarda su
 * hash. Si alguien la pierde, no se recupera, se genera otra.
 *
 * @property int $id
 * @property string $name
 * @property string $prefix
 * @property string $key_hash
 * @property list<string>|null $allowed_origins
 * @property int|null $rate_limit
 * @property int|null $created_by
 * @property Carbon|null $last_used_at
 * @property string|null $last_used_ip
 * @property int $requests_count
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 */
class ApiKey extends Model
{
    /** @use HasFactory<ApiKeyFactory> */
    use HasFactory;

    /**
     * Prefijo con el que empiezan todas las claves, para reconocerlas de un
     * vistazo si aparecen en un registro o en el código de la web.
     */
    public const TOKEN_PREFIX = 'blog_';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'prefix',
        'key_hash',
        'allowed_origins',
        'rate_limit',
        'created_by',
        'expires_at',
    ];

    /** @var list<string> */
    protected $hidden = ['key_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_origins' => 'array',
            'rate_limit' => 'integer',
            'requests_count' => 'integer',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Genera una clave nueva y devuelve el modelo junto al texto en claro.
     *
     * Es la única vez que ese texto existe: el llamante debe enseñarlo y
     * olvidarlo.
     *
     * @param  list<string>  $allowedOrigins
     * @return array{key: self, plain: string}
     */
    public static function generate(
        string $name,
        array $allowedOrigins = [],
        ?int $rateLimit = null,
        ?int $createdBy = null,
        ?Carbon $expiresAt = null,
    ): array {
        $plain = self::TOKEN_PREFIX.Str::random(48);

        $key = self::create([
            'name' => $name,
            'prefix' => mb_substr($plain, 0, 12),
            'key_hash' => hash('sha256', $plain),
            'allowed_origins' => $allowedOrigins === [] ? null : $allowedOrigins,
            'rate_limit' => $rateLimit,
            'created_by' => $createdBy,
            'expires_at' => $expiresAt,
        ]);

        return ['key' => $key, 'plain' => $plain];
    }

    /**
     * Busca la clave activa que corresponde a un texto en claro.
     *
     * Se compara por hash, así que un atacante con acceso a la base de datos
     * no obtiene claves utilizables.
     */
    public static function findByPlain(string $plain): ?self
    {
        $plain = trim($plain);

        if ($plain === '') {
            return null;
        }

        return self::query()->where('key_hash', hash('sha256', $plain))->first();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    /**
     * Motivo por el que no sirve, para poder explicarlo en la respuesta.
     */
    public function unusableReason(): ?string
    {
        return match (true) {
            $this->isRevoked() => 'La clave de API ha sido revocada.',
            $this->isExpired() => 'La clave de API ha caducado.',
            default => null,
        };
    }

    /**
     * ¿Acepta esta clave peticiones desde este origen?
     *
     * Sin lista de orígenes se acepta cualquiera: es lo razonable para una
     * clave que se usa desde el servidor, donde no hay navegador ni CORS.
     */
    public function allowsOrigin(?string $origin): bool
    {
        $allowed = $this->allowed_origins ?? [];

        if ($allowed === []) {
            return true;
        }

        return $origin !== null && in_array(rtrim($origin, '/'), $allowed, true);
    }

    public function limitPerMinute(): int
    {
        return $this->rate_limit ?? (int) config('api.rate_limit');
    }

    /**
     * Deja constancia del uso.
     *
     * Se escribe sin tocar `updated_at` ni disparar eventos: es contabilidad,
     * no una modificación del recurso.
     */
    public function recordUsage(?string $ip): void
    {
        self::query()->whereKey($this->getKey())->update([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
            'requests_count' => $this->requests_count + 1,
        ]);
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }

    /**
     * @param  Builder<ApiKey>  $query
     * @return Builder<ApiKey>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->where(fn (Builder $inner) => $inner->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
