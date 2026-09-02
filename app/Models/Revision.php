<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RevisionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Versión guardada de un contenido.
 *
 * Es polimórfica: el historial y el guardado automático sirven igual para un
 * artículo que para cualquier otro contenido largo del panel.
 *
 * @property int $id
 * @property string|null $revisionable_type
 * @property int|null $revisionable_id
 * @property int|null $user_id
 * @property RevisionType $type
 * @property array<string, mixed> $snapshot
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
class Revision extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'revisions';

    /** @var list<string> */
    protected $fillable = [
        'revisionable_type',
        'revisionable_id',
        'user_id',
        'type',
        'snapshot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RevisionType::class,
            'snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * El contenido al que pertenece esta versión.
     *
     * @return MorphTo<Model, $this>
     */
    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Resumen legible de la versión, para el listado del historial.
     */
    public function summary(): string
    {
        /** @var string $title */
        $title = $this->snapshot['title'] ?? '(sin título)';

        return $title;
    }
}
