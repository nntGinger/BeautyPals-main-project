<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', 'BeautyPals - sàn thương mại điện tử mỹ phẩm chính hãng.')">
    <title>@yield('title', 'Trang chủ') | {{ config('app.name', 'BeautyPals') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-screen flex flex-col bg-brand-50/40 text-ink antialiased">
    @include('partials.navbar')

    <main class="flex-1">
        @include('partials.flash')
        @yield('content')
    </main>

    @include('partials.footer')

    @stack('scripts')
</body>
</html>
