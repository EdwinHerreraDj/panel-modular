<?php

declare(strict_types=1);

namespace App\Modules;

use App\Models\ApiKey;
use App\Models\Media;
use App\Models\Redirect;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Values\ContentType;

/**
 * Tipos de contenido del propio núcleo.
 *
 * El núcleo no es un módulo —siempre está—, pero sus modelos aparecen en las
 * mismas columnas polimórficas y en el mismo filtro de auditoría, así que se
 * declaran igual y el registro los suma a los de los módulos activos.
 */
final class CoreContentTypes
{
    /**
     * @return list<ContentType>
     */
    public static function all(): array
    {
        return [
            new ContentType(
                model: User::class,
                alias: 'core.user',
                singular: 'Usuario',
                plural: 'Usuarios',
                editUrl: fn (int|string $id): string => route('users.edit', $id),
            ),
            new ContentType(
                model: Media::class,
                alias: 'core.media',
                singular: 'Archivo',
                plural: 'Archivos',
            ),
            new ContentType(
                model: Setting::class,
                alias: 'core.setting',
                singular: 'Configuración',
                plural: 'Configuración',
                editUrl: fn (int|string $id): string => route('settings.edit'),
                // No tiene título ni nombre: la descripción por defecto se
                // quedaría en «Configuración: » con el nombre del blog dentro,
                // que es justo lo que no se está preguntando.
                describe: fn (): string => 'Configuración del blog',
            ),
            new ContentType(
                model: ApiKey::class,
                alias: 'core.api_key',
                singular: 'Clave de API',
                plural: 'Claves de API',
            ),
            new ContentType(
                model: Redirect::class,
                alias: 'core.redirect',
                singular: 'Redirección',
                plural: 'Redirecciones',
                editUrl: fn (int|string $id): string => route('redirects.index'),
            ),

        ];
    }
}
