<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Ayudantes de prueba del blog
|--------------------------------------------------------------------------
|
| Los carga tests/Pest.php junto con los de cualquier otro módulo. Están
| aquí y no en el núcleo porque describen un formulario del blog.
|
*/

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function articlePayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Cómo elegir las persianas de casa',
        'excerpt' => 'Una guía breve para no equivocarse.',
        'content' => '<p>El primer paso es medir el hueco.</p>',
        'status' => 'draft',
        'visibility' => 'public',
        'position' => 0,
        'is_indexable' => true,
    ], $overrides);
}
