<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\ActivityLog;
use App\Models\AuthLog;
use App\Models\Media;
use App\Models\User;
use App\Modules\ModuleRegistry;
use App\Modules\Values\DashboardAction;
use App\Modules\Values\DashboardAlert;
use App\Modules\Values\DashboardCard;
use App\Modules\Values\DashboardWidget;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel de inicio.
 *
 * El núcleo aporta el saludo, la actividad reciente y las cifras de la
 * plataforma. Todo lo que tiene que ver con el contenido lo ponen los módulos
 * activos, ya resuelto para quien mira: cada rol sigue viendo lo suyo, pero esa
 * decisión la toma quien conoce el contenido, no esta pantalla.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $modules = app(ModuleRegistry::class)->dashboard($user);

        return Inertia::render('Dashboard', [
            'greeting' => [
                'name' => $user->name,
                'role' => $user->roleName()?->label(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],

            'actions' => array_map(
                fn (DashboardAction $action): array => [
                    'label' => $action->label,
                    'href' => $action->href,
                    'icon' => $action->icon,
                ],
                $modules->actions,
            ),

            'cards' => array_map(
                fn (DashboardCard $card): array => [
                    'key' => $card->key,
                    'label' => $card->label,
                    'count' => $card->count,
                    'icon' => $card->icon,
                    'tone' => $card->tone,
                    'hint' => $card->hint,
                    'href' => $card->route === null ? null : route($card->route, $card->query),
                ],
                $modules->cards,
            ),

            'alerts' => array_map(
                fn (DashboardAlert $alert): array => [
                    'key' => $alert->key,
                    'text' => $alert->text,
                    'tone' => $alert->tone,
                    'action_label' => $alert->actionLabel,
                    'action_href' => $alert->actionHref,
                ],
                $modules->alerts,
            ),

            'widgets' => array_map(
                fn (DashboardWidget $widget): array => [
                    'component' => $widget->component,
                    'props' => $widget->props,
                    'area' => $widget->area,
                ],
                $modules->widgets,
            ),

            'activity' => $user->can(Permission::AuditView->value) ? $this->recentActivity() : null,
            'platform' => $user->can(Permission::AuditView->value) ? $this->platformStats() : null,
        ]);
    }

    /**
     * Últimos cambios sobre el contenido.
     *
     * @return list<array<string, mixed>>
     */
    private function recentActivity(): array
    {
        return ActivityLog::query()
            ->with('user:id,name')
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (ActivityLog $log): array => [
                'id' => $log->id,
                'description' => $log->description ?? $log->event->label(),
                'author_name' => $log->user?->name,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Cifras de la plataforma, solo para quien puede ver la auditoría.
     *
     * @return array<string, mixed>
     */
    private function platformStats(): array
    {
        return [
            'users' => User::query()->count(),
            'media' => Media::query()->count(),
            'logins_today' => AuthLog::query()
                ->where('event', 'login')
                ->whereDate('created_at', now()->toDateString())
                ->count(),
            'failed_today' => AuthLog::query()
                ->whereIn('event', ['login_failed', 'login_blocked'])
                ->whereDate('created_at', now()->toDateString())
                ->count(),
        ];
    }
}
