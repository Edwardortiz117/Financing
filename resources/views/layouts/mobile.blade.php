<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="theme-color" content="#22c55e">
    <title>@yield('title', 'Financing') — {{ config('app.name', 'Financing') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface text-ink antialiased">
    <div class="mx-auto min-h-screen max-w-md safe-bottom">
        @if (session('status'))
            <div class="mx-4 mt-4 rounded-2xl bg-brand/10 px-4 py-3 text-sm text-brand-dark">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mx-4 mt-4 rounded-2xl bg-red-50 px-4 py-3 text-sm text-bad">
                <ul class="list-disc pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>

    @include('mobile.partials.nav-bottom')
    @include('mobile.partials.modals')
    @include('mobile.partials.invoice-modal')
</body>
</html>
