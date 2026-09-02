<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Modules\ModuleRegistry;
use App\Support\ImageConstraints;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Redes admitidas en los datos estructurados.
     *
     * @var list<string>
     */
    public const NETWORKS = [
        'facebook', 'instagram', 'x', 'linkedin', 'youtube', 'tiktok', 'pinterest', 'threads',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SettingsManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // --- General ---------------------------------------------------
            'site_name' => ['required', 'string', 'max:150'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'public_url' => ['nullable', 'string', 'max:255', 'url:https,http'],
            'locale' => ['required', 'string', 'max:5', Rule::in(config('locales.available'))],
            'timezone' => ['required', 'string', 'max:64', 'timezone'],

            // Los campos de imagen son opcionales: solo llegan cuando se
            // sustituye la imagen actual.
            'logo' => ['nullable', ...ImageConstraints::for('logo')->validationRules()],
            'logo_dark' => ['nullable', ...ImageConstraints::for('logo')->validationRules()],
            'favicon' => ['nullable', ...ImageConstraints::for('favicon')->validationRules()],
            'og_image' => ['nullable', ...ImageConstraints::for('og_image')->validationRules()],

            // Marcas para borrar una imagen ya guardada.
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_logo_dark' => ['sometimes', 'boolean'],
            'remove_favicon' => ['sometimes', 'boolean'],
            'remove_og_image' => ['sometimes', 'boolean'],

            // --- SEO -------------------------------------------------------
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'title_template' => ['required', 'string', 'max:120', 'regex:/:title/'],
            'is_indexable' => ['required', 'boolean'],
            'twitter_card' => ['required', Rule::in(['summary', 'summary_large_image'])],

            // --- Redes sociales --------------------------------------------
            'organization_name' => ['nullable', 'string', 'max:150'],
            'social_profiles' => ['nullable', 'array', 'max:10'],
            'social_profiles.*.network' => ['required', 'string', Rule::in(self::NETWORKS)],
            'social_profiles.*.url' => ['required', 'string', 'max:255', 'url:https'],
            ...$this->moduleRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $formats = [
            'logo' => ImageConstraints::for('logo'),
            'logo_dark' => ImageConstraints::for('logo'),
            'favicon' => ImageConstraints::for('favicon'),
            'og_image' => ImageConstraints::for('og_image'),
        ];

        $messages = [
            'public_url.url' => 'Introduce una URL completa, por ejemplo: https://www.miempresa.es',
            'title_template.regex' => 'La plantilla debe contener :title, que se sustituye por el título de cada página.',
            'timezone.timezone' => 'Selecciona una zona horaria válida.',
            'meta_description.max' => 'La meta descripción no debería superar los 160 caracteres para que no se corte en Google.',
            'meta_title.max' => 'El meta título no debería superar los 70 caracteres para que no se corte en Google.',
            'social_profiles.*.url.url' => 'Introduce la URL completa del perfil, empezando por https://',
            'social_profiles.*.network.in' => 'Selecciona una red de la lista.',
        ];

        foreach ($formats as $field => $constraints) {
            $messages["{$field}.mimetypes"] = "El archivo debe ser una imagen {$constraints->formatsLabel()}.";
            $messages["{$field}.image"] = 'El archivo debe ser una imagen.';
            $messages["{$field}.dimensions"] = sprintf(
                'Las dimensiones deben estar entre %d×%d y %d×%d píxeles.',
                $constraints->minWidth,
                $constraints->minHeight,
                $constraints->maxWidth,
                $constraints->maxHeight,
            );
        }

        return $messages;
    }

    /**
     * Reglas de los ajustes que aporta cada módulo activo.
     *
     * Se construyen a partir de lo que declara el módulo, así que un campo que
     * el módulo no reconozca no llega ni a validarse: se descarta.
     *
     * @return array<string, mixed>
     */
    private function moduleRules(): array
    {
        $rules = ['modules' => ['sometimes', 'array']];

        foreach (app(ModuleRegistry::class)->settings() as $module => $settings) {
            $rules['modules.'.$module] = ['sometimes', 'array'];

            foreach ($settings as $setting) {
                // `sometimes` por delante: lo que el módulo pida se comprueba
                // solo si llega. Así un formulario que no incluya la sección de
                // un módulo no falla por un campo que ni siquiera se le ha
                // enseñado a quien lo rellena.
                $rules['modules.'.$module.'.'.$setting->key] = ['sometimes', ...$setting->rules];
            }
        }

        return $rules;
    }

    /**
     * Nombres legibles de los ajustes de los módulos, para los mensajes.
     *
     * @return array<string, string>
     */
    private function moduleAttributes(): array
    {
        $names = [];

        foreach (app(ModuleRegistry::class)->settings() as $module => $settings) {
            foreach ($settings as $setting) {
                $names['modules.'.$module.'.'.$setting->key] = mb_strtolower($setting->label);
            }
        }

        return $names;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'site_name' => 'nombre del sitio',
            'tagline' => 'descripción breve',
            'public_url' => 'URL pública',
            'locale' => 'idioma',
            'timezone' => 'zona horaria',
            'logo' => 'logotipo',
            'logo_dark' => 'logotipo para modo oscuro',
            'favicon' => 'favicon',
            'og_image' => 'imagen para redes sociales',
            'meta_title' => 'meta título',
            'meta_description' => 'meta descripción',
            'title_template' => 'plantilla de título',
            'is_indexable' => 'indexación',
            'twitter_card' => 'formato de tarjeta',
            'organization_name' => 'nombre de la organización',
            ...$this->moduleAttributes(),
        ];
    }

    protected function prepareForValidation(): void
    {
        // Los formularios con archivos viajan como multipart, así que los
        // booleanos llegan en texto.
        $this->merge([
            'is_indexable' => $this->boolean('is_indexable'),
        ]);
    }
}
