<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AuthEvent;
use App\Models\AuthLog;
use App\Support\UserAgent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Registro de accesos a la plataforma.
 *
 * Responde a «quién ha entrado y desde dónde»: entradas, salidas, intentos
 * fallidos y cambios de contraseña. Solo lo ve quien tiene `audit.view`.
 */
class AuthLogController extends Controller
{
    /**
     * Periodos que se ofrecen en el filtro, en días.
     *
     * @var array<string, int>
     */
    private const PERIODS = [
        'today' => 0,
        'week' => 7,
        'month' => 30,
    ];

    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => (string) $request->string('status'),   // tipo de evento
            'extra' => (string) $request->string('extra'),     // periodo
        ];

        $logs = $this->query($filters)
            ->with('user:id,name,email')
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AuthLog $log): array => [
                'id' => $log->id,
                'event' => $log->event->value,
                'event_label' => $log->event->label(),
                'event_tone' => $this->tone($log->event),
                'user_id' => $log->user_id,
                'user_name' => $log->user?->name,
                'email' => $log->email,
                'ip_address' => $log->ip_address,
                'device' => UserAgent::describe($log->user_agent),
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('AuthLogs/Index', [
            'logs' => $logs,
            'filters' => $filters + ['trashed' => false, 'sort' => 'created_at', 'direction' => 'desc'],
            'eventOptions' => array_map(
                fn (AuthEvent $event): array => ['value' => $event->value, 'label' => $event->label()],
                AuthEvent::cases(),
            ),
            'periodOptions' => [
                ['value' => 'today', 'label' => 'Hoy'],
                ['value' => 'week', 'label' => 'Últimos 7 días'],
                ['value' => 'month', 'label' => 'Últimos 30 días'],
            ],
            'summary' => $this->summary(),
        ]);
    }

    /**
     * @param  array{search: string, status: string, extra: string}  $filters
     * @return Builder<AuthLog>
     */
    private function query(array $filters): Builder
    {
        return AuthLog::query()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';

                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('email', 'like', $term)
                        ->orWhere('ip_address', 'like', $term)
                        ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', $term));
                });
            })
            ->when(
                AuthEvent::tryFrom($filters['status']) !== null,
                fn (Builder $query) => $query->where('event', $filters['status']),
            )
            ->when(
                array_key_exists($filters['extra'], self::PERIODS),
                fn (Builder $query) => $filters['extra'] === 'today'
                    ? $query->whereDate('created_at', now()->toDateString())
                    : $query->where('created_at', '>=', now()->subDays(self::PERIODS[$filters['extra']])),
            );
    }

    /**
     * Cifras de cabecera: lo que interesa mirar antes que la lista.
     *
     * @return array<string, int|null>
     */
    private function summary(): array
    {
        $today = AuthLog::query()->whereDate('created_at', now()->toDateString());

        return [
            'logins_today' => (clone $today)->where('event', AuthEvent::Login->value)->count(),
            'failed_today' => (clone $today)
                ->whereIn('event', [AuthEvent::LoginFailed->value, AuthEvent::LoginBlocked->value])
                ->count(),
            'people_today' => (clone $today)
                ->where('event', AuthEvent::Login->value)
                ->distinct()
                ->count('user_id'),
            'failed_week' => AuthLog::query()
                ->whereIn('event', [AuthEvent::LoginFailed->value, AuthEvent::LoginBlocked->value])
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
        ];
    }

    private function tone(AuthEvent $event): string
    {
        return match ($event) {
            AuthEvent::Login => 'success',
            AuthEvent::LoginFailed => 'warning',
            AuthEvent::LoginBlocked => 'danger',
            AuthEvent::Logout => 'neutral',
            AuthEvent::PasswordChanged, AuthEvent::PasswordReset => 'info',
            AuthEvent::PasswordResetRequested => 'neutral',
        };
    }
}
