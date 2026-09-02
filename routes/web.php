<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureUserIsActive;
use App\Modules\ModuleRegistry;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas del área privada
|--------------------------------------------------------------------------
|
| Este archivo solo monta el grupo autenticado y carga los archivos de rutas:
| primero los del núcleo y después los de cada módulo activo.
|
| Los módulos se cargan aquí dentro y no en su propio proveedor para que la
| pila de middleware siga definida en un único sitio: un módulo no puede
| olvidarse de exigir sesión iniciada porque no es él quien lo decide.
|
*/

Route::redirect('/', '/panel')->name('home');

Route::middleware(['auth', EnsureUserIsActive::class, EnsurePasswordIsChanged::class])->group(function (): void {
    Route::get('panel', DashboardController::class)->name('dashboard');

    require __DIR__.'/panel/nucleo.php';

    foreach (app(ModuleRegistry::class)->routeFiles('web') as $moduleRoutes) {
        require $moduleRoutes;
    }
});
