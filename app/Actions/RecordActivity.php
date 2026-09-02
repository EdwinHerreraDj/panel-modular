<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Deja constancia de quién hizo qué sobre un elemento de contenido.
 */
final class RecordActivity
{
    /**
     * Campos que no se vuelcan nunca al registro.
     *
     * @var list<string>
     */
    private const REDACTED = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'settings',
    ];

    /**
     * @param  array<string, mixed>|null  $changes
     */
    public function handle(
        Model $subject,
        ActivityEvent $event,
        ?string $description = null,
        ?array $changes = null,
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => Auth::id(),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'event' => $event,
            'description' => $description,
            'changes' => $changes === null ? null : $this->redact($changes),
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Registra una modificación con los campos que realmente han cambiado.
     */
    public function forUpdate(Model $subject, ?string $description = null): ActivityLog
    {
        $changes = $subject->getChanges();
        unset($changes['updated_at']);

        return $this->handle($subject, ActivityEvent::Updated, $description, $changes);
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function redact(array $changes): array
    {
        foreach (self::REDACTED as $field) {
            if (array_key_exists($field, $changes)) {
                $changes[$field] = '[oculto]';
            }
        }

        return $changes;
    }
}
