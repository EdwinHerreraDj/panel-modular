<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMXPath;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonySanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Limpieza del HTML del contenido antes de guardarlo.
 *
 * El editor del panel produce HTML correcto, pero eso no significa nada: la
 * petición puede venir de cualquier sitio. Se aplica una lista blanca estricta
 * de elementos, atributos y dominios, y se descarta todo lo demás.
 */
final class HtmlSanitizer
{
    private static ?SymfonySanitizer $sanitizer = null;

    public function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $clean = self::sanitizer()->sanitize($html);
        $clean = $this->removeGuttedElements($clean);

        return mb_substr($clean, 0, (int) config('sanitizer.max_length', 500_000));
    }

    /**
     * Retira los elementos que se han quedado inservibles tras la limpieza.
     *
     * Al descartar un `src` o un `href` peligroso, el sanitizador conserva la
     * etiqueta vacía: un `<iframe>` sin origen se vería como un recuadro en
     * blanco y un `<a>` sin destino, como un enlace que no lleva a ninguna
     * parte. Aquí se elimina el envoltorio y se conserva el texto.
     */
    private function removeGuttedElements(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $document = new DOMDocument;

        // El HTML ya viene normalizado por el sanitizador; se silencian los
        // avisos de libxml sobre etiquetas sueltas del fragmento.
        $previous = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="raiz">'.$html.'</div>',
            LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);

        // Sin origen no hay nada que mostrar: fuera el elemento entero.
        foreach ($xpath->query('//iframe[not(@src)] | //img[not(@src)]') ?: [] as $node) {
            $node->parentNode?->removeChild($node);
        }

        // Un enlace sin destino se sustituye por su propio texto.
        foreach (iterator_to_array($xpath->query('//a[not(@href)]') ?: []) as $node) {
            while ($node->firstChild !== null) {
                $node->parentNode?->insertBefore($node->firstChild, $node);
            }

            $node->parentNode?->removeChild($node);
        }

        $root = $document->getElementById('raiz');

        if ($root === null) {
            return $html;
        }

        $result = '';

        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return $result;
    }

    /**
     * Texto plano del contenido, para el buscador y el tiempo de lectura.
     */
    public function toPlainText(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // Los bloques se separan con un espacio para que no se peguen palabras
        // de párrafos distintos al quitar las etiquetas.
        $spaced = preg_replace('/<(\/?)(p|br|div|li|h[1-6]|tr|blockquote)[^>]*>/i', ' ', $html) ?? $html;

        $text = html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Tiempo estimado de lectura, en minutos. Nunca menos de uno.
     */
    public function readingMinutes(?string $html): int
    {
        $text = $this->toPlainText($html);

        if ($text === '') {
            return 0;
        }

        $words = count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $perMinute = max(1, (int) config('sanitizer.words_per_minute', 200));

        return max(1, (int) ceil($words / $perMinute));
    }

    private static function sanitizer(): SymfonySanitizer
    {
        if (self::$sanitizer !== null) {
            return self::$sanitizer;
        }

        $config = new HtmlSanitizerConfig;

        /** @var array<string, list<string>> $elements */
        $elements = config('sanitizer.allowed_elements', []);

        foreach ($elements as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        /** @var list<string>|null $linkHosts */
        $linkHosts = config('sanitizer.link_hosts');
        $config = $config->allowLinkHosts($linkHosts);

        /** @var list<string>|null $mediaHosts */
        $mediaHosts = config('sanitizer.media_hosts');
        $config = $config->allowMediaHosts($mediaHosts);

        // Solo esquemas que no ejecutan nada: fuera `javascript:` y `data:`.
        $config = $config
            ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
            ->allowMediaSchemes(['https'])
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->forceHttpsUrls()
            // Los enlaces externos no deben poder manipular la ventana de origen.
            ->forceAttribute('a', 'rel', 'noopener noreferrer');

        return self::$sanitizer = new SymfonySanitizer($config);
    }

    /**
     * Reinicia la instancia cacheada. Solo lo necesitan las pruebas que
     * cambian la configuración en caliente.
     */
    public static function flush(): void
    {
        self::$sanitizer = null;
    }
}
