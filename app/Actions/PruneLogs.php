<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\AuthLog;
use Illuminate\Database\Eloquent\Builder;

/**
 * Borra los registros de auditoría más antiguos que el plazo configurado.
 *
 * En un alojamiento compartido el disco y la base de datos son pequeños, y
 * estas dos tablas crecen con cada visita al panel. Sin poda, en un par de años
 * pesan más que todo el contenido del blog.
 *
 * Se borra por lotes: un `DELETE` de cientos de miles de filas puede bloquear
 * la tabla el tiempo suficiente para que se note.
 */
final class PruneLogs
{
    private const BATCH = 1000;

    /**
     * @return array{auth: int, activity: int}
     */
    public function handle(?int $days = null): array
    {
        $days = $days ?? (int) config('security.logs.retention_days', 365);

        if ($days <= 0) {
            return ['auth' => 0, 'activity' => 0];
        }

        $before = now()->subDays($days);

        return [
            'auth' => $this->prune(AuthLog::query()->where('created_at', '<', $before)),
            'activity' => $this->prune(ActivityLog::query()->where('created_at', '<', $before)),
        ];
    }

    /**
     * ¿Hay algo que podar? Consulta barata sobre el índice de fecha.
     */
    public function hasStale(?int $days = null): bool
    {
        $days = $days ?? (int) config('security.logs.retention_days', 365);

        if ($days <= 0) {
            return false;
        }

        $before = now()->subDays($days);

        return AuthLog::query()->where('created_at', '<', $before)->exists()
            || ActivityLog::query()->where('created_at', '<', $before)->exists();
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private function prune(Builder $query): int
    {
        $total = 0;

        do {
            $deleted = $query->clone()->limit(self::BATCH)->delete();
            $total += $deleted;
        } while ($deleted === self::BATCH);

        return $total;
    }
}
