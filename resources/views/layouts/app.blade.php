<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'BeautyPals')</title>
    <style>
        :root {
            --bg: #faf6f3;
            --card: #ffffff;
            --ink: #2b211c;
            --muted: #7a6a63;
            --line: #eadfd8;
            --accent: #c45c6a;
            --accent-dark: #a34452;
            --ok: #2f7d57;
            --danger: #b42318;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", system-ui, sans-serif;
            background: var(--bg);
            color: var(--ink);
        }
        a { color: var(--accent-dark); text-decoration: none; }
        a:hover { text-decoration: underline; }
        header.app-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.5rem;
            background: #fff;
            border-bottom: 1px solid var(--line);
        }
        .brand { font-weight: 700; color: var(--ink); font-size: 1.15rem; }
        .nav { display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap; }
        .nav form { margin: 0; }
        main { max-width: 880px; margin: 2rem auto; padding: 0 1rem 3rem; }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 8px 24px rgba(43, 33, 28, 0.04);
        }
        h1 { margin: 0 0 0.5rem; font-size: 1.6rem; }
        .muted { color: var(--muted); }
        label { display: block; font-weight: 600; margin: 0.9rem 0 0.35rem; }
        input {
            width: 100%;
            padding: 0.7rem 0.8rem;
            border: 1px solid var(--line);
            border-radius: 10px;
            font-size: 1rem;
        }
        button, .btn {
            display: inline-block;
            border: 0;
            border-radius: 999px;
            padding: 0.7rem 1.2rem;
            background: var(--accent);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover, .btn:hover { background: var(--accent-dark); text-decoration: none; }
        .btn-ghost { background: transparent; color: var(--ink); border: 1px solid var(--line); }
        .error { color: var(--danger); font-size: 0.92rem; margin: 0.35rem 0 0; }
        .flash-error {
            background: #fde7e5;
            color: var(--danger);
            padding: 0.75rem 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        .stack { display: grid; gap: 0.4rem; }
        .meta { margin-top: 1rem; font-size: 0.95rem; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/beautypals.css') }}">
</head>
<body>
    <header class="app-header">
        <a class="brand" href="{{ route('home') }}">Beauty<span>Pals.</span></a>
        <nav class="nav">
            @auth
                <span>{{ auth()->user()->name }} ({{ auth()->user()->role }})</span>
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.vendors.index') }}">Quản lý Vendor</a>
                @endif
                <a href="{{ auth()->user()->homePath() }}">Khu vực của tôi</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-ghost">Đăng xuất</button>
                </form>
            @else
                <a href="{{ route('login') }}">Đăng nhập</a>
                <a class="btn" href="{{ route('register') }}">Đăng ký</a>
            @endauth
        </nav>
    </header>
    <main>
        @yield('content')
    </main>
    <script src="{{ asset('js/password-toggle.js') }}" defer></script>
    <script src="{{ asset('js/login-countdown.js') }}" defer></script>
</body>
</html>
