<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ActivityEvent;
use App\Models\ActivityLog;
use App\Modules\ModuleRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Auditoría del contenido: quién cambió qué y cuándo.
 *
 * Complementa al registro de accesos. Aquel responde «quién ha entrado»; este,
 * «quién tocó este artículo».
 */
class ActivityLogController extends Controller
{
    /** @var array<string, int> Periodos ofrecidos en el filtro, en días. */
    private const PERIODS = ['today' => 0, 'week' => 7, 'month' => 30];

    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => (string) $request->string('status'),   // tipo de evento
            'extra' => (string) $request->string('extra'),     // periodo
            'subject' => (string) $request->string('subject'),
        ];

        $logs = $this->query($filters)
            ->with('user:id,name')
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ActivityLog $log): array => [
                'id' => $log->id,
                'event' => $log->event->value,
                'event_label' => $log->event->label(),
                'event_tone' => $this->tone($log->event),
                'description' => $log->description ?? $log->event->label(),
                'subject_label' => $this->subjectLabel($log->subject_type),
                'subject_id' => $log->subject_id,
                'subject_url' => $this->subjectUrl($log),
                'user_name' => $log->user?->name,
                'ip_address' => $log->ip_address,
                'changes' => $this->readableChanges($log),
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('ActivityLogs/Index', [
            'logs' => $logs,
            'filters' => $filters + ['trashed' => false, 'sort' => 'created_at', 'direction' => 'desc'],
            'eventOptions' => array_map(
                fn (ActivityEvent $event): array => ['value' => $event->value, 'label' => $event->label()],
                ActivityEvent::cases(),
            ),
            'subjectOptions' => array_map(
                fn (string $alias, string $label): array => ['value' => $alias, 'label' => $label],
                array_keys($this->subjects()),
                array_values($this->subjects()),
            ),
            'periodOptions' => [
                ['value' => 'today', 'label' => 'Hoy'],
                ['value' => 'week', 'label' => 'Últimos 7 días'],
                ['value' => 'month', 'label' => 'Últimos 30 días'],
            ],
        ]);
    }

    /**
     * @param  array{search: string, status: string, extra: string, subject: string}  $filters
     * @return Builder<ActivityLog>
     */
    private function query(array $filters): Builder
    {
        return ActivityLog::query()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';

                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('description', 'like', $term)
                        ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', $term));
                });
            })
            ->when(
                ActivityEvent::tryFrom($filters['status']) !== null,
                fn (Builder $query) => $query->where('event', $filters['status']),
            )
            ->when(
                array_key_exists($filters['subject'], $this->subjects()),
                fn (Builder $query) => $query->where('subject_type', $filters['subject']),
            )
            ->when(
                array_key_exists($filters['extra'], self::PERIODS),
                fn (Builder $query) => $filters['extra'] === 'today'
                    ? $query->whereDate('created_at', now()->toDateString())
                    : $query->where('created_at', '>=', now()->subDays(self::PERIODS[$filters['extra']])),
            );
    }

    /**
     * Los tipos auditables indexados por el alias que guarda la base de datos.
     *
     * @return array<string, string>
     */
    /**
     * Tipos auditables, indexados por el alias que guarda la base de datos.
     *
     * Antes era una constante con once clases escritas a mano. Ahora la
     * componen el núcleo y los módulos activos: un módulo aparece en el filtro
     * «Sobre qué» sin tocar este controlador.
     *
     * @return array<string, string>
     */
    private function subjects(): array
    {
        $subjects = [];

        foreach (app(ModuleRegistry::class)->contentTypes() as $type) {
            $subjects[$type->alias] = $type->plural;
        }

        return $subjects;
    }

    private function subjectLabel(string $type): string
    {
        return app(ModuleRegistry::class)->contentType($type)->singular
            ?? class_basename($type);
    }

    /**
     * Enlace al elemento, para los tipos que declaran tener pantalla propia.
     */
    private function subjectUrl(ActivityLog $log): ?string
    {
        return app(ModuleRegistry::class)
            ->contentType($log->subject_type)
            ?->urlFor($log->subject_id);
    }

    /**
     * Nombres de los campos modificados, en español.
     *
     * Se enseña qué cambió, no los valores: el registro de auditoría se lee para
     * saber dónde mirar, y volcar contenidos enteros lo haría ilegible.
     *
     * @return list<string>
     */
    private function readableChanges(ActivityLog $log): array
    {
        $names = [
            'title' => 'título',
            'slug' => 'dirección',
            'excerpt' => 'extracto',
            'content' => 'contenido',
            'status' => 'estado',
            'visibility' => 'visibilidad',
            'published_at' => 'fecha de publicación',
            'scheduled_for' => 'programación',
            'featured_media_id' => 'imagen destacada',
            'name' => 'nombre',
            'email' => 'correo',
            'description' => 'descripción',
            'meta_title' => 'título SEO',
            'meta_description' => 'descripción SEO',
            'is_indexable' => 'indexable',
            'position' => 'orden',
        ];

        $fields = array_map(
            fn (string $field): string => $names[$field] ?? str_replace('_', ' ', $field),
            array_keys($log->changes ?? []),
        );

        // MySQL no conserva el orden de las claves dentro de una columna JSON,
        // así que se ordena aquí: si no, la misma entrada se vería distinta
        // según cómo la guardara la base de datos.
        sort($fields, SORT_NATURAL | SORT_FLAG_CASE);

        return $fields;
    }

    private function tone(ActivityEvent $event): string
    {
        return match ($event) {
            ActivityEvent::Created => 'success',
            ActivityEvent::Updated => 'info',
            ActivityEvent::Deleted => 'danger',
            ActivityEvent::Restored => 'warning',
        };
    }
}
