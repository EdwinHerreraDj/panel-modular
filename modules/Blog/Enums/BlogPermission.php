<?php

declare(strict_types=1);

namespace Modules\Blog\Enums;

/**
 * Permisos del blog.
 *
 * Van prefijados por el módulo para que un cliente pueda dar el catálogo a una
 * persona sin darle el blog. El prefijo se decidió antes de la primera
 * instalación de un segundo módulo: cambiarlo después obliga a re-sembrar los
 * permisos de cada cliente.
 *
 * El enum es para el código del módulo, que quiere constantes con tipo. Lo que
 * ve el resto de la plataforma es la lista de `ModulePermission` que declara
 * BlogModule.
 */
enum BlogPermission: string
{
    // Artículos
    case ArticlesView = 'blog.articles.view';
    case ArticlesCreate = 'blog.articles.create';
    case ArticlesUpdateOwn = 'blog.articles.update.own';
    case ArticlesUpdateAny = 'blog.articles.update.any';
    case ArticlesDelete = 'blog.articles.delete';
    case ArticlesRestore = 'blog.articles.restore';
    case ArticlesPublish = 'blog.articles.publish';
    case ArticlesSchedule = 'blog.articles.schedule';
    case ArticlesApprove = 'blog.articles.approve';

    // Taxonomías
    case CategoriesManage = 'blog.categories.manage';
    case TagsManage = 'blog.tags.manage';

    public function label(): string
    {
        return match ($this) {
            self::ArticlesView => 'Ver artículos',
            self::ArticlesCreate => 'Crear artículos',
            self::ArticlesUpdateOwn => 'Editar sus propios artículos',
            self::ArticlesUpdateAny => 'Editar cualquier artículo',
            self::ArticlesDelete => 'Enviar artículos a la papelera',
            self::ArticlesRestore => 'Restaurar artículos',
            self::ArticlesPublish => 'Publicar artículos',
            self::ArticlesSchedule => 'Programar publicaciones',
            self::ArticlesApprove => 'Aprobar o rechazar contenido',
            self::CategoriesManage => 'Gestionar categorías',
            self::TagsManage => 'Gestionar etiquetas',
        };
    }

    /**
     * Agrupación para mostrar los permisos ordenados en la interfaz.
     */
    public function group(): string
    {
        return match ($this) {
            self::CategoriesManage, self::TagsManage => 'Taxonomías',
            default => 'Artículos',
        };
    }
}
