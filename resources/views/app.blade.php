<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="/images/favicon.png" sizes="16x16">

    {{-- Evita el parpadeo de tema claro antes de que React monte. --}}
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('color-theme');
                var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (stored === 'dark' || (stored === null && prefersDark)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>

    @routes
    @viteReactRefresh
    @vite(['resources/scss/app.scss', 'resources/js/app.tsx'])
    @inertiaHead
</head>

<body class="bg-neutral-100 font-sans dark:bg-neutral-800 dark:text-white">
    @inertia
</body>

</html>
