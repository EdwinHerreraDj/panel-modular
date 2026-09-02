<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Permission;
use App\Models\Setting;
use App\Models\User;
use App\Modules\ModuleRegistry;

/**
 * Manual para quien usa el panel.
 *
 * No es documentación técnica: va dirigido al cliente, que no sabe qué es un
 * slug ni una caché y no tiene por qué. Por eso está escrito en el lenguaje del
 * panel y explica los porqués, no los mecanismos.
 *
 * Una sola fuente para las dos formas de leerlo: la pantalla de ayuda y el
 * archivo que se puede imprimir o enviar por correo. Así no se contradicen.
 *
 * Cada sección declara qué permiso hace falta para verla: a un autor no le
 * sirve leer cómo se dan de alta usuarios.
 */
class UserManual
{
    /**
     * @return list<array{
     *     key: string,
     *     title: string,
     *     icon: string,
     *     permission: string|null,
     *     blocks: list<array{type: string, content: mixed}>
     * }>
     */
    public static function sections(): array
    {
        $sections = [
            [10, self::firstSteps()],
            [50, self::images()],
            [70, self::trash()],
            [80, self::settings()],
            [90, self::users()],
            [100, self::questions()],
        ];

        // Cada módulo activo aporta las suyas y se intercalan por posición: el
        // blog ocupa de la 20 a la 60, entre los primeros pasos y la papelera.
        foreach (app(ModuleRegistry::class)->manualSections() as $section) {
            $sections[] = [$section->position, $section->toArray()];
        }

        usort($sections, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return array_map(fn (array $section): array => $section[1], $sections);
    }

    /**
     * Secciones que esta persona puede aprovechar.
     *
     * @return list<array<string, mixed>>
     */
    public static function forUser(User $user): array
    {
        return array_values(array_filter(
            self::sections(),
            fn (array $section): bool => $section['permission'] === null
                || $user->can($section['permission']),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private static function firstSteps(): array
    {
        return [
            'key' => 'primeros-pasos',
            'title' => 'Primeros pasos',
            'icon' => 'ri-flag-line',
            'permission' => null,
            'blocks' => [
                ['type' => 'text', 'content' => 'Este panel sirve para gestionar el contenido de tu web. '
                    .'Lo que escribas aquí aparece en la web pública sin que tengas que tocar nada más.'],

                ['type' => 'heading', 'content' => 'La contraseña'],
                ['type' => 'text', 'content' => 'La primera contraseña te la damos nosotros, así que la '
                    .'conocemos. El panel te obliga a cambiarla la primera vez que entras. No es un trámite: '
                    .'hasta que la cambies, hay alguien más que puede entrar con tu cuenta.'],
                ['type' => 'tip', 'content' => 'Una frase larga que recuerdes es más segura que una palabra '
                    .'corta con símbolos raros. Y no reutilices la del correo: si filtran una, caen todas.'],

                ['type' => 'heading', 'content' => 'Si la pierdes'],
                ['type' => 'text', 'content' => 'En la pantalla de acceso, pulsa «He olvidado mi contraseña». '
                    .'Te llega un correo con un enlace que caduca en una hora. Si no aparece, mira en la '
                    .'carpeta de correo no deseado.'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function images(): array
    {
        return [
            'key' => 'imagenes',
            'title' => 'La biblioteca de imágenes',
            'icon' => 'ri-image-line',
            'permission' => Permission::MediaManage->value,
            'blocks' => [
                ['type' => 'text', 'content' => 'Todas las imágenes que subes quedan aquí y se pueden '
                    .'reutilizar en cualquier artículo. No hace falta volver a subirlas.'],

                ['type' => 'heading', 'content' => 'Qué se puede subir'],
                ['type' => 'text', 'content' => 'JPG, PNG y WebP, hasta 5 MB y 4000×4000 píxeles. Si una '
                    .'foto se pasa, redúcela antes: el panel te lo dirá en lugar de fallar sin explicación.'],
                ['type' => 'tip', 'content' => 'De cada imagen se generan versiones más pequeñas '
                    .'automáticamente. La web usa la que le conviene en cada sitio, así que las páginas '
                    .'cargan rápido sin que tengas que hacer nada.'],

                ['type' => 'heading', 'content' => 'No se puede borrar lo que está en uso'],
                ['type' => 'text', 'content' => 'Si intentas eliminar una imagen que aparece en un '
                    .'artículo, el panel no te deja y te dice **en cuál**. Es deliberado: borrarla dejaría '
                    .'un hueco en una página publicada.'],
                ['type' => 'text', 'content' => 'Quita la imagen de ese artículo y ya podrás eliminarla.'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function trash(): array
    {
        return [
            'key' => 'papelera',
            'title' => 'La papelera',
            'icon' => 'ri-delete-bin-line',
            'permission' => null,
            'blocks' => [
                ['type' => 'text', 'content' => 'Cuando eliminas algo no desaparece: va a la papelera. '
                    .'Marca «Ver papelera» en cualquier listado para encontrarlo y restaurarlo.'],
                ['type' => 'text', 'content' => 'Lo que está en la papelera **deja de verse en la web** al '
                    .'instante, aunque siga aquí.'],
                ['type' => 'warning', 'content' => 'En la biblioteca de imágenes hay además un borrado '
                    .'definitivo, que sí borra los archivos del disco. Ese **no tiene vuelta atrás**. Solo '
                    .'está disponible para el administrador y siempre pide confirmación.'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function settings(): array
    {
        return [
            'key' => 'configuracion',
            'title' => 'Configuración del blog',
            'icon' => 'ri-settings-3-line',
            'permission' => Permission::SettingsManage->value,
            'blocks' => [
                ['type' => 'text', 'content' => 'Aquí está el nombre del blog, el logotipo, el icono de la '
                    .'pestaña y los textos que Google y las redes sociales usan por defecto.'],
                ['type' => 'text', 'content' => 'Los datos de SEO de esta pantalla se aplican a todo lo que '
                    .'no tenga los suyos propios. Cada artículo puede sobrescribirlos en su pestaña de SEO, '
                    .'pero no hace falta: si los dejas vacíos se usan el título y el extracto.'],
                ['type' => 'tip', 'content' => 'La descripción para buscadores funciona mejor entre 120 y '
                    .'160 caracteres. Más larga, Google la corta.'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function users(): array
    {
        return [
            'key' => 'usuarios',
            'title' => 'Usuarios y permisos',
            'icon' => 'ri-team-line',
            'permission' => Permission::UsersManage->value,
            'blocks' => [
                ['type' => 'text', 'content' => 'Cada persona entra con su propia cuenta. No compartas una '
                    .'cuenta entre varios: si algo sale mal, no hay forma de saber quién hizo qué.'],
                ['type' => 'table', 'content' => [
                    'headers' => ['Rol', 'Qué puede hacer'],
                    'rows' => [
                        ['Superadministrador', 'Todo, incluidos usuarios, configuración y la parte técnica.'],
                        ['Editor', 'Todo el contenido: escribir, revisar, publicar y gestionar imágenes.'],
                        ['Revisor', 'Ver todo y aprobar o rechazar, pero no publicar.'],
                        ['Autor', 'Escribir lo suyo y enviarlo a revisión. No publica.'],
                        ['Lector', 'Solo consultar.'],
                    ],
                ]],
                ['type' => 'text', 'content' => 'Al crear una cuenta le pones una contraseña provisional. '
                    .'Esa persona tendrá que cambiarla la primera vez que entre, porque tú la conoces.'],
                ['type' => 'text', 'content' => 'Si alguien deja de trabajar contigo, **suspéndelo** en vez '
                    .'de borrarlo: pierde el acceso de inmediato y se conserva el registro de lo que hizo.'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function questions(): array
    {
        return [
            'key' => 'dudas',
            'title' => 'Dudas frecuentes',
            'icon' => 'ri-question-line',
            'permission' => null,
            'blocks' => [
                ['type' => 'question', 'content' => [
                    'He publicado un artículo y no se ve en la web',
                    'Espera unos minutos. La web guarda una copia de los listados durante un rato para ir '
                    .'rápida, y se actualiza sola. Si pasados diez minutos sigue sin verse, avísanos.',
                ]],
                ['type' => 'question', 'content' => [
                    'He cambiado el título y la dirección sigue igual',
                    'Es lo correcto. Al publicar, la dirección se congela para no romper los enlaces que ya '
                    .'estén compartidos. Si de verdad necesitas cambiarla, usa el botón de regenerar: se '
                    .'guarda una redirección desde la antigua.',
                ]],
                ['type' => 'question', 'content' => [
                    'No me deja borrar una imagen',
                    'Está en uso en algún artículo, y el aviso te dice en cuál. Quítala de ahí y podrás '
                    .'borrarla.',
                ]],
                ['type' => 'question', 'content' => [
                    'He perdido lo que estaba escribiendo',
                    'Vuelve a abrir el artículo: si había una copia automática reciente, el panel te ofrece '
                    .'recuperarla. Y en «Versiones» están todas las veces que guardaste.',
                ]],
                ['type' => 'question', 'content' => [
                    'Se ha publicado algo que no quería',
                    'Cámbialo a «Archivado» y desaparece de la web al momento. Después ya lo revisas con calma.',
                ]],
                ['type' => 'question', 'content' => [
                    'La foto que subo se ve mal recortada',
                    'La web recorta según dónde se muestre. Sube imágenes apaisadas y con lo importante '
                    .'centrado, y evita textos dentro de la foto: son lo primero que se pierde al recortar.',
                ]],
                ['type' => 'question', 'content' => [
                    'Me he quedado fuera y dice que la sesión ha caducado',
                    'Pasa si dejas el panel abierto mucho rato sin usarlo. Vuelve a entrar; lo que hubieras '
                    .'guardado sigue ahí.',
                ]],
            ],
        ];
    }

    /**
     * El manual completo en Markdown, para imprimirlo o enviarlo.
     */
    public static function markdown(): string
    {
        $lines = [
            '# Manual de '.Setting::current()->site_name,
            '',
            'Guía para gestionar el contenido de tu web desde el panel.',
            '',
            '---',
            '',
        ];

        foreach (self::sections() as $section) {
            $lines[] = '## '.$section['title'];
            $lines[] = '';

            foreach ($section['blocks'] as $block) {
                match ($block['type']) {
                    'heading' => $lines[] = '### '.$block['content'],
                    'text' => $lines[] = (string) $block['content'],
                    'tip' => $lines[] = '> 💡 '.$block['content'],
                    'warning' => $lines[] = '> ⚠️ '.$block['content'],
                    'question' => $lines = [
                        ...$lines,
                        '**'.$block['content'][0].'**',
                        '',
                        $block['content'][1],
                    ],
                    'table' => $lines = [...$lines, ...self::markdownTable($block['content'])],
                    default => null,
                };

                $lines[] = '';
            }

            $lines[] = '---';
            $lines[] = '';
        }

        $lines[] = '¿Alguna duda que no esté aquí? Escríbenos.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param  array{headers: list<string>, rows: list<list<string>>}  $table
     * @return list<string>
     */
    private static function markdownTable(array $table): array
    {
        $lines = [
            '| '.implode(' | ', $table['headers']).' |',
            '|'.str_repeat('---|', count($table['headers'])),
        ];

        foreach ($table['rows'] as $row) {
            $lines[] = '| '.implode(' | ', $row).' |';
        }

        return $lines;
    }
}
