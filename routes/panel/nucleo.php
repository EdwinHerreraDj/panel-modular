<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ApiConnectionController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\AuthLogController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MediaPickerController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas del núcleo
|--------------------------------------------------------------------------
|
| Lo que no depende del tipo de contenido: la cuenta propia, la biblioteca de
| archivos, las redirecciones, los usuarios, la configuración, la conexión con
| la web y la auditoría.
|
| Se cargan dentro del grupo autenticado de routes/web.php.
|
*/

/*
|--------------------------------------------------------------------------
| Cuenta propia
|--------------------------------------------------------------------------
*/
// La ayuda la ve cualquiera que pueda entrar al panel.
Route::get('ayuda', [HelpController::class, 'index'])->name('help.index');
Route::get('ayuda/manual', [HelpController::class, 'download'])->name('help.download');

Route::get('mi-cuenta', [ProfileController::class, 'edit'])->name('profile.edit');
Route::put('mi-cuenta', [ProfileController::class, 'update'])->name('profile.update');

/*
|--------------------------------------------------------------------------
| Biblioteca multimedia
|--------------------------------------------------------------------------
*/
Route::get('biblioteca', [MediaController::class, 'index'])->name('media.index');
Route::post('biblioteca', [MediaController::class, 'store'])->name('media.store');
Route::get('biblioteca/{media}', [MediaController::class, 'show'])->name('media.show');
Route::put('biblioteca/{media}', [MediaController::class, 'update'])->name('media.update');
Route::delete('biblioteca/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
Route::post('biblioteca/{media}/restaurar', [MediaController::class, 'restore'])
    ->withTrashed()
    ->name('media.restore');
// Antes de la ruta con parámetro, para que «papelera» no se confunda con
// el identificador de un archivo.
Route::delete('biblioteca/papelera/vaciar', [MediaController::class, 'emptyTrash'])
    ->name('media.trash.empty');
Route::delete('biblioteca/{media}/definitivo', [MediaController::class, 'purge'])
    ->withTrashed()
    ->name('media.purge');

// Selector que se abre desde los formularios.
Route::get('selector-imagenes', [MediaPickerController::class, 'index'])->name('media.picker');
Route::post('selector-imagenes', [MediaPickerController::class, 'store'])->name('media.picker.store');

/*
|--------------------------------------------------------------------------
| Redirecciones
|--------------------------------------------------------------------------
*/
Route::middleware('can:'.Permission::RedirectsManage->value)->group(function (): void {
    Route::get('redirecciones', [RedirectController::class, 'index'])->name('redirects.index');
    Route::post('redirecciones', [RedirectController::class, 'store'])->name('redirects.store');
    Route::delete('redirecciones/{redirect}', [RedirectController::class, 'destroy'])
        ->name('redirects.destroy');
});

/*
|--------------------------------------------------------------------------
| Configuración del blog
|--------------------------------------------------------------------------
*/
Route::get('configuracion', [SettingController::class, 'edit'])->name('settings.edit');
Route::post('configuracion', [SettingController::class, 'update'])->name('settings.update');

/*
|--------------------------------------------------------------------------
| Usuarios (solo superadministrador)
|--------------------------------------------------------------------------
*/
Route::get('usuarios', [UserController::class, 'index'])->name('users.index');
Route::get('usuarios/crear', [UserController::class, 'create'])->name('users.create');
Route::post('usuarios', [UserController::class, 'store'])->name('users.store');
Route::get('usuarios/{user}/editar', [UserController::class, 'edit'])->name('users.edit');
Route::put('usuarios/{user}', [UserController::class, 'update'])->name('users.update');
Route::put('usuarios/{user}/contrasena', [UserController::class, 'resetPassword'])
    ->name('users.password');
Route::delete('usuarios/{user}', [UserController::class, 'destroy'])->name('users.destroy');
Route::post('usuarios/{user}/restaurar', [UserController::class, 'restore'])
    ->withTrashed()
    ->name('users.restore');

/*
|--------------------------------------------------------------------------
| Conexión con la web pública (API)
|--------------------------------------------------------------------------
*/
Route::middleware('can:'.Permission::ApiKeysManage->value)->group(function (): void {
    Route::get('conexion-web', [ApiConnectionController::class, 'index'])->name('api.connection');
    Route::post('conexion-web/probar', [ApiConnectionController::class, 'run'])->name('api.connection.run');
    Route::get('conexion-web/documentacion', [ApiConnectionController::class, 'documentation'])
        ->name('api.connection.docs');
    Route::post('conexion-web/vaciar-cache', [ApiConnectionController::class, 'flushCache'])
        ->name('api.connection.flush');

    Route::post('conexion-web/claves', [ApiKeyController::class, 'store'])->name('api-keys.store');
    Route::post('conexion-web/claves/{apiKey}/revocar', [ApiKeyController::class, 'revoke'])
        ->name('api-keys.revoke');
    Route::delete('conexion-web/claves/{apiKey}', [ApiKeyController::class, 'destroy'])
        ->name('api-keys.destroy');
});

/*
|--------------------------------------------------------------------------
| Módulos (solo superadministrador)
|--------------------------------------------------------------------------
*/
Route::middleware('can:'.Permission::ModulesManage->value)->group(function (): void {
    Route::get('modulos', [ModuleController::class, 'index'])->name('modules.index');
    Route::put('modulos', [ModuleController::class, 'update'])->name('modules.update');
});

/*
|--------------------------------------------------------------------------
| Auditoría de accesos
|--------------------------------------------------------------------------
*/
Route::middleware('can:'.Permission::AuditView->value)->group(function (): void {
    Route::get('registro-accesos', [AuthLogController::class, 'index'])->name('auth-logs.index');
    Route::get('auditoria', [ActivityLogController::class, 'index'])->name('activity-logs.index');
});
