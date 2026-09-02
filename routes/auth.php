<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de autenticación
|--------------------------------------------------------------------------
|
| No existe registro público: las cuentas las crean los administradores desde
| el área privada (fase de usuarios).
|
*/

Route::middleware('guest')->group(function (): void {
    Route::get('acceso', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('acceso', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:20,1');

    Route::get('recuperar-contrasena', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('recuperar-contrasena', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('password.email');

    Route::get('restablecer-contrasena/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('restablecer-contrasena', [NewPasswordController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('salir', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Cambio de contraseña: obligatorio tras un alta o un restablecimiento
    // hecho por un administrador, y disponible siempre desde la cuenta propia.
    Route::get('cambiar-contrasena', [ChangePasswordController::class, 'create'])
        ->name('password.change');
    Route::put('cambiar-contrasena', [ChangePasswordController::class, 'update'])
        ->middleware('throttle:10,1')
        ->name('password.change.update');
});
