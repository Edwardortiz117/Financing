<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel de Presupuesto') — {{ config('app.name', 'Financing') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#121212] text-white antialiased">
    <div class="mx-auto max-w-3xl px-5 py-8">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-[#333] bg-[#1e1e1e] px-4 py-3 text-sm text-[#81c784]">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-[#f44336]/40 bg-[#1e1e1e] px-4 py-3 text-sm text-[#f44336]">
                <ul class="list-disc pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>
</body>
</html>
