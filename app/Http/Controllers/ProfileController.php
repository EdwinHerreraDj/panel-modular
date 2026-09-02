<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cuenta propia de la persona que ha iniciado sesión.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $role = $user->roleName();

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'role_label' => $role?->label() ?? 'Sin rol',
                'role_tone' => $role?->tone() ?? 'neutral',
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'password_changed_at' => $user->password_changed_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $user->update($request->safe()->only(['name', 'email']));

        return redirect()
            ->route('profile.edit')
            ->with('success', 'Tus datos se han actualizado correctamente.');
    }
}
