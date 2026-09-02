<?php

declare(strict_types=1);

namespace App\Modules;

use App\Models\User;
use App\Modules\Values\DashboardContribution;

/**
 * Punto de partida para un módulo.
 *
 * Todo devuelve vacío, de modo que crear uno cueste lo que cueste lo que de
 * verdad aporta: un módulo que solo tiene pantallas implementa `key`, `name`,
 * `permissions` y `menu`, y se olvida del resto hasta que lo necesite.
 */
abstract class BaseModule implements Module
{
    public function permissions(): array
    {
        return [];
    }

    public function menu(): array
    {
        return [];
    }

    public function apiEndpoints(): array
    {
        return [];
    }

    public function contentTypes(): array
    {
        return [];
    }

    public function dashboard(User $user): DashboardContribution
    {
        return DashboardContribution::empty();
    }

    public function manualSections(): array
    {
        return [];
    }

    public function settings(): array
    {
        return [];
    }

    public function deferredTasks(): array
    {
        return [];
    }

    public function publicCounts(): array
    {
        return [];
    }

    public function sitemapSections(): array
    {
        return [];
    }
}
