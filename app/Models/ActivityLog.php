<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Entrada del registro de auditoría de contenido.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $subject_type
 * @property int $subject_id
 * @property ActivityEvent $event
 * @property string|null $description
 * @property array<string, mixed>|null $changes
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'subject_type',
        'subject_id',
        'event',
        'description',
        'changes',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => ActivityEvent::class,
            'changes' => 'array',
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
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
