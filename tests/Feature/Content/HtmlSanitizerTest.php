<?php

declare(strict_types=1);

use App\Support\HtmlSanitizer;

/**
 * El contenido de los artículos se guarda ya limpio: nunca se confía en el HTML
 * que llega del navegador, aunque lo haya generado nuestro propio editor.
 */
beforeEach(function (): void {
    HtmlSanitizer::flush();
    $this->sanitizer = new HtmlSanitizer;
});

it('conserva las etiquetas habituales de un artículo', function (): void {
    $html = '<h2>Título</h2><p>Un <strong>párrafo</strong> con <em>énfasis</em>.</p>'
        .'<ul><li>Uno</li><li>Dos</li></ul><blockquote>Una cita</blockquote>';

    $clean = $this->sanitizer->sanitize($html);

    expect($clean)->toContain('<h2>Título</h2>')
        ->and($clean)->toContain('<strong>párrafo</strong>')
        ->and($clean)->toContain('<li>Uno</li>')
        ->and($clean)->toContain('<blockquote>');
});

it('elimina las etiquetas script', function (): void {
    $clean = $this->sanitizer->sanitize('<p>Hola</p><script>alert("xss")</script>');

    expect($clean)->toContain('Hola')
        ->and($clean)->not->toContain('<script')
        ->and($clean)->not->toContain('alert');
});

it('elimina los manejadores de eventos en línea', function (): void {
    $clean = $this->sanitizer->sanitize('<p onclick="robar()">Texto</p><img src="https://x.test/a.png" onerror="robar()">');

    expect($clean)->not->toContain('onclick')
        ->and($clean)->not->toContain('onerror')
        ->and($clean)->not->toContain('robar');
});

it('descarta los enlaces con javascript: y conserva su texto', function (): void {
    $clean = $this->sanitizer->sanitize('<p><a href="javascript:alert(1)">Pulsa aquí</a></p>');

    expect($clean)->not->toContain('javascript:')
        ->and($clean)->toContain('Pulsa aquí')
        // El envoltorio sobrante también desaparece: un enlace sin destino no
        // debe quedar en el contenido.
        ->and($clean)->not->toContain('<a');
});

it('no deja iframes vacíos cuando el origen no está permitido', function (): void {
    $clean = $this->sanitizer->sanitize(
        '<p>Texto</p><iframe src="https://sitio-malicioso.test/panel"></iframe>',
    );

    expect($clean)->toContain('Texto')
        ->and($clean)->not->toContain('<iframe');
});

it('no deja imágenes vacías cuando el origen no está permitido', function (): void {
    $clean = $this->sanitizer->sanitize('<p>Texto</p><img src="data:text/html;base64,AAAA">');

    expect($clean)->toContain('Texto')
        ->and($clean)->not->toContain('<img');
});

it('descarta las imágenes con data: incrustado', function (): void {
    $clean = $this->sanitizer->sanitize('<img src="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">');

    expect($clean)->not->toContain('data:');
});

it('elimina las etiquetas no permitidas conservando su texto', function (): void {
    $clean = $this->sanitizer->sanitize('<p>Antes</p><form><input name="x"></form><style>body{display:none}</style>');

    expect($clean)->toContain('Antes')
        ->and($clean)->not->toContain('<form')
        ->and($clean)->not->toContain('<input')
        ->and($clean)->not->toContain('<style');
});

it('admite iframes de los dominios de vídeo permitidos', function (): void {
    $clean = $this->sanitizer->sanitize(
        '<iframe src="https://www.youtube-nocookie.com/embed/abc123" width="640" height="360"></iframe>',
    );

    expect($clean)->toContain('<iframe')
        ->and($clean)->toContain('youtube-nocookie.com');
});

it('rechaza los iframes de dominios no permitidos', function (): void {
    $clean = $this->sanitizer->sanitize('<iframe src="https://sitio-malicioso.test/panel"></iframe>');

    expect($clean)->not->toContain('sitio-malicioso.test')
        ->and($clean)->not->toContain('<iframe');
});

it('añade rel de seguridad a los enlaces', function (): void {
    $clean = $this->sanitizer->sanitize('<a href="https://www.ejemplo.es">Ejemplo</a>');

    expect($clean)->toContain('noopener')
        ->and($clean)->toContain('noreferrer');
});

it('extrae el texto plano separando los bloques', function (): void {
    $text = $this->sanitizer->toPlainText('<p>Primero</p><p>Segundo</p><ul><li>Tercero</li></ul>');

    expect($text)->toBe('Primero Segundo Tercero');
});

it('calcula el tiempo de lectura a partir del texto', function (): void {
    $words = implode(' ', array_fill(0, 400, 'palabra'));

    expect($this->sanitizer->readingMinutes("<p>{$words}</p>"))->toBe(2)
        ->and($this->sanitizer->readingMinutes('<p>Muy corto</p>'))->toBe(1)
        ->and($this->sanitizer->readingMinutes(''))->toBe(0);
});

it('devuelve cadena vacía si no hay contenido', function (): void {
    expect($this->sanitizer->sanitize(null))->toBe('')
        ->and($this->sanitizer->sanitize('   '))->toBe('');
});
