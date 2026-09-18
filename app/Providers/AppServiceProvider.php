<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\AuthenticateApiKey;
use App\Models\ApiKey;
use App\Models\Setting;
use App\Models\User;
use App\Support\PasswordRules;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureUrls();
        $this->configurePasswords();
        $this->configureNotifications();
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configureFavicon();

        Vite::prefetch(concurrency: 3);
    }

    /**
     * Favicon del panel, tomado de Configuración.
     *
     * Va en la vista raíz y no en las props de Inertia porque se pinta en el
     * `<head>` antes de que monte React. Si no se ha subido ninguno se usa el
     * de la plantilla.
     */
    private function configureFavicon(): void
    {
        View::composer('app', function (ViewContract $view): void {
            $settings = Setting::current();

            $view->with(
                'panelFavicon',
                $settings->imageUrl($settings->favicon_path) ?? '/images/favicon.png',
            );
        });
    }

    /**
     * Límite de la API pública.
     *
     * Se cuenta por clave cuando la hay: así dos webs distintas no se gastan
     * la cuota la una a la otra, y una que se desmadre no deja fuera al resto.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            $key = $request->attributes->get(AuthenticateApiKey::ATTRIBUTE);

            if ($key instanceof ApiKey) {
                return Limit::perMinute($key->limitPerMinute())->by('clave:'.$key->getKey());
            }

            return Limit::perMinute((int) config('api.rate_limit'))->by('ip:'.$request->ip());
        });
    }

    /**
     * El superadministrador puede con todo, salvo en lo que afecta a las
     * cuentas de usuario.
     *
     * Ahí la decisión se delega siempre en UserPolicy, porque incluye
     * salvaguardas que deben aplicarse también a él: nadie se elimina a sí
     * mismo ni se cambia su propio rol.
     */
    private function configureAuthorization(): void
    {
        /** @param array<int, mixed> $arguments */
        Gate::before(function (User $user, string $ability, array $arguments = []): ?bool {
            $subject = $arguments[0] ?? null;

            if ($subject instanceof User || $subject === User::class) {
                return null;
            }

            return $user->isSuperAdmin() ? true : null;
        });
    }

    /**
     * Detecta pronto los errores de acceso a datos: atributos no cargados,
     * asignación masiva indebida y carga diferida (origen de las N+1).
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::unguard(false);
    }

    private function configureUrls(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    private function configurePasswords(): void
    {
        Password::defaults(fn (): Password => PasswordRules::default());
    }

    /**
     * Correo de restablecimiento en español.
     */
    private function configureNotifications(): void
    {
        ResetPassword::toMailUsing(function (User $notifiable, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], absolute: false));

            $minutes = config('auth.passwords.users.expire', 60);

            return (new MailMessage)
                ->subject('Restablece tu contraseña · '.config('app.name'))
                ->greeting('Hola, '.$notifiable->name)
                ->line('Has recibido este correo porque se ha solicitado restablecer la contraseña de tu cuenta.')
                ->action('Restablecer contraseña', url($url))
                ->line("Este enlace caducará en {$minutes} minutos.")
                ->line('Si no has solicitado el cambio, no tienes que hacer nada: tu contraseña seguirá siendo la misma.')
                ->salutation('Un saludo, el equipo de '.config('app.name'));
        });
    }
}
