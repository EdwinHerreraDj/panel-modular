import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import react from '@vitejs/plugin-react'
import Icons from 'unplugin-icons/vite'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/scss/app.scss', 'resources/js/app.tsx'],
            // Las rutas y las páginas de los módulos también recargan en
            // caliente: si no, tocar un módulo obliga a reiniciar Vite.
            refresh: [
                'resources/views/**',
                'routes/**',
                'modules/*/routes/**',
                'modules/*/resources/js/**',
            ],
        }),
        react(),
        // Los iconos de Iconify se compilan a SVG en línea: sin CDN ni peticiones en runtime.
        Icons({ compiler: 'jsx', jsx: 'react' }),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            // Un módulo importa lo suyo con `@modules/Blog/…` en lugar de
            // contar cuántos `../` hay hasta la raíz.
            '@modules': fileURLToPath(new URL('./modules', import.meta.url)),
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                // El SCSS de la plantilla usa @import (obsoleto en Dart Sass pero
                // todavía funcional). Se silencia el aviso para no ensuciar el build.
                silenceDeprecations: ['import', 'global-builtin'],
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**', '**/plantilla-base-diseño/**'],
        },
    },
})
