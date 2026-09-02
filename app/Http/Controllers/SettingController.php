<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RecordActivity;
use App\Actions\StoreUploadedImage;
use App\Enums\Permission;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;
use App\Modules\ModuleRegistry;
use App\Modules\ModuleSettings;
use App\Modules\Values\ModuleSetting;
use App\Support\ImageConstraints;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración del sitio.
 *
 * Lo que hay aquí vale para toda la instalación: cómo se llama, su marca y su
 * SEO por defecto. Cada módulo activo añade debajo lo suyo, si tiene algo que
 * configurar, y esta pantalla no necesita saber qué es.
 */
class SettingController extends Controller
{
    /**
     * Lo que cada módulo activo quiere configurar, con su valor actual.
     *
     * @return list<array<string, mixed>>
     */
    private function moduleSettings(): array
    {
        $registry = app(ModuleRegistry::class);
        $store = app(ModuleSettings::class);
        $sections = [];

        foreach ($registry->settings() as $key => $settings) {
            $sections[] = [
                'module' => $key,
                'title' => $registry->get($key)?->name() ?? $key,
                'fields' => array_map(fn (ModuleSetting $setting): array => [
                    'key' => $setting->key,
                    'label' => $setting->label,
                    'type' => $setting->type,
                    'hint' => $setting->hint,
                    'value' => $store->get($key, $setting->key),
                ], $settings),
            ];
        }

        return $sections;
    }

    /**
     * Guarda lo que han enviado los módulos.
     *
     * El almacén solo acepta las claves que el módulo declara, así que enviar
     * una de más desde el navegador no cuela nada.
     */
    private function saveModuleSettings(UpdateSettingsRequest $request): void
    {
        /** @var array<string, array<string, mixed>> $submitted */
        $submitted = $request->validated('modules') ?? [];
        $store = app(ModuleSettings::class);

        foreach ($submitted as $module => $values) {
            $store->put($module, $values);
        }
    }

    /**
     * Campos de imagen y el tipo de restricciones que les corresponde.
     *
     * @var array<string, array{column: string, type: string}>
     */
    private const IMAGE_FIELDS = [
        'logo' => ['column' => 'logo_path', 'type' => 'logo'],
        'logo_dark' => ['column' => 'logo_dark_path', 'type' => 'logo'],
        'favicon' => ['column' => 'favicon_path', 'type' => 'favicon'],
        'og_image' => ['column' => 'og_image_path', 'type' => 'og_image'],
    ];

    public function __construct(
        private readonly StoreUploadedImage $storeImage,
        private readonly RecordActivity $recordActivity,
    ) {}

    public function edit(): Response
    {
        $this->authorize(Permission::SettingsManage->value);

        $setting = Setting::current();

        return Inertia::render('Settings/Edit', [
            'settings' => [
                'site_name' => $setting->site_name,
                'tagline' => $setting->tagline,
                'public_url' => $setting->public_url,
                'locale' => $setting->locale,
                'timezone' => $setting->timezone,
                'meta_title' => $setting->meta_title,
                'meta_description' => $setting->meta_description,
                'title_template' => $setting->title_template,
                'is_indexable' => $setting->is_indexable,
                'twitter_card' => $setting->twitter_card,
                'organization_name' => $setting->organization_name,
                'social_profiles' => $setting->social_profiles ?? [],
            ],
            'images' => [
                'logo' => $setting->imageUrl($setting->logo_path),
                'logo_dark' => $setting->imageUrl($setting->logo_dark_path),
                'favicon' => $setting->imageUrl($setting->favicon_path),
                'og_image' => $setting->imageUrl($setting->og_image_path),
            ],
            'imageConstraints' => [
                'logo' => ImageConstraints::for('logo')->toArray(),
                'logo_dark' => ImageConstraints::for('logo')->toArray(),
                'favicon' => ImageConstraints::for('favicon')->toArray(),
                'og_image' => ImageConstraints::for('og_image')->toArray(),
            ],
            'moduleSettings' => $this->moduleSettings(),
            'localeOptions' => $this->localeOptions(),
            'timezoneOptions' => $this->timezoneOptions(),
            'networkOptions' => array_map(
                fn (string $network): array => ['value' => $network, 'label' => ucfirst($network)],
                UpdateSettingsRequest::NETWORKS,
            ),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $setting = Setting::current();

        DB::transaction(function () use ($request, $setting): void {
            $setting->fill($request->safe()->only([
                'site_name',
                'tagline',
                'public_url',
                'locale',
                'timezone',
                'meta_title',
                'meta_description',
                'title_template',
                'is_indexable',
                'twitter_card',
                'organization_name',
            ]));

            $setting->social_profiles = $request->validated('social_profiles') ?? [];

            $this->applyImages($request, $setting);

            $setting->save();

            $this->saveModuleSettings($request);

            $this->recordActivity->forUpdate($setting, 'Modificación de la configuración del sitio');
        });

        return redirect()
            ->route('settings.edit')
            ->with('success', 'Configuración guardada correctamente.');
    }

    /**
     * Sustituye o elimina las imágenes según lo enviado.
     *
     * El archivo antiguo se borra solo después de guardar el nuevo, para no
     * quedarse sin imagen si algo falla por el camino.
     */
    private function applyImages(UpdateSettingsRequest $request, Setting $setting): void
    {
        foreach (self::IMAGE_FIELDS as $field => $definition) {
            $column = $definition['column'];
            $previous = $setting->{$column};

            $file = $request->file($field);

            if ($file instanceof UploadedFile) {
                $setting->{$column} = $this->storeImage->handle($file, $definition['type']);
                $this->storeImage->delete($previous);

                continue;
            }

            if ($request->boolean("remove_{$field}")) {
                $setting->{$column} = null;
                $this->storeImage->delete($previous);
            }
        }
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function localeOptions(): array
    {
        /** @var list<string> $locales */
        $locales = config('locales.available');
        /** @var array<string, string> $labels */
        $labels = config('locales.labels');

        return array_map(
            fn (string $locale): array => ['value' => $locale, 'label' => $labels[$locale] ?? $locale],
            $locales,
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function timezoneOptions(): array
    {
        /** @var list<string> $timezones */
        $timezones = config('locales.timezones');

        return array_map(
            fn (string $timezone): array => ['value' => $timezone, 'label' => $timezone],
            $timezones,
        );
    }
}
