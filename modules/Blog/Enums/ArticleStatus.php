<?php

declare(strict_types=1);

namespace Modules\Blog\Enums;

/**
 * Estados del flujo editorial de un artículo.
 *
 * Cada transición exige un permiso concreto: no basta con conocer la URL para
 * publicar algo.
 */
enum ArticleStatus: string
{
    /** En redacción. Solo lo ve quien puede entrar al panel. */
    case Draft = 'draft';

    /** El autor lo ha enviado a revisión. */
    case PendingReview = 'pending_review';

    /** Un revisor lo ha aprobado; falta publicarlo. */
    case Approved = 'approved';

    /** Aprobado y con fecha futura: se publicará solo. */
    case Scheduled = 'scheduled';

    /** Visible en la web pública. */
    case Published = 'published';

    /** Retirado de la web, pero conservado. */
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::PendingReview => 'Pendiente de revisión',
            self::Approved => 'Aprobado',
            self::Scheduled => 'Programado',
            self::Published => 'Publicado',
            self::Archived => 'Archivado',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::PendingReview => 'warning',
            self::Approved => 'info',
            self::Scheduled => 'info',
            self::Published => 'success',
            self::Archived => 'danger',
        };
    }

    /**
     * ¿Se sirve por la API pública?
     */
    public function isPublic(): bool
    {
        return $this === self::Published;
    }

    /**
     * Estados a los que se puede pasar desde este.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingReview, self::Approved, self::Scheduled, self::Published, self::Archived],
            self::PendingReview => [self::Draft, self::Approved, self::Scheduled, self::Published],
            self::Approved => [self::Draft, self::PendingReview, self::Scheduled, self::Published, self::Archived],
            self::Scheduled => [self::Draft, self::Approved, self::Published, self::Archived],
            self::Published => [self::Draft, self::Archived],
            self::Archived => [self::Draft, self::Published],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return $target === $this || in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Permiso necesario para dejar un artículo en este estado.
     *
     * Devolver null significa que basta con poder editar el artículo.
     */
    public function requiredPermission(): ?BlogPermission
    {
        return match ($this) {
            self::Published => BlogPermission::ArticlesPublish,
            self::Scheduled => BlogPermission::ArticlesSchedule,
            self::Approved => BlogPermission::ArticlesApprove,
            self::Archived => BlogPermission::ArticlesPublish,
            self::Draft, self::PendingReview => null,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
