<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Horizon</title>

        <script>
            (() => {
                try {
                    const scheme = localStorage.getItem('horizonColorScheme') ?? 'system';
                    const dark = scheme === 'dark'
                        || (scheme === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);

                    document.documentElement.classList.toggle('dark', dark);
                    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
                } catch {
                    // The application can still render when storage is unavailable.
                }
            })();
        </script>

        @inject('assets', 'NckRtl\HorizonNewDawn\Assets\AssetManifest')
        <link rel="icon" href="{{ $assets->favicon() }}" type="image/svg+xml" sizes="any">
        @foreach ($assets->styles() as $stylesheet)
            <link rel="stylesheet" href="{{ $stylesheet }}">
        @endforeach
        <script type="module" src="{{ $assets->script() }}"></script>

        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
