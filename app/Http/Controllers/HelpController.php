<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\UserManual;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Ayuda dentro del panel.
 *
 * El manual se enseña aquí porque un archivo en una carpeta no lo lee nadie:
 * la ayuda tiene que estar donde surge la duda. Y se puede descargar para
 * imprimirlo o mandarlo por correo, que es lo que suelen pedir.
 */
class HelpController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);

        return Inertia::render('Help/Index', [
            // Solo lo que esta persona puede aprovechar: a un autor no le sirve
            // leer cómo se dan de alta usuarios.
            'sections' => UserManual::forUser($user),
            'siteName' => Setting::current()->site_name,
        ]);
    }

    public function download(): SymfonyResponse
    {
        $name = Str::slug(Setting::current()->site_name ?: 'blog');

        return response(UserManual::markdown(), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="manual-'.$name.'.md"',
        ]);
    }
}
