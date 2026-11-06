<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
            --warn: #b54708;
            --danger: #b42318;
        }
        * { box-sizing: border-box; }
        [hidden] { display: none !important; }
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
            flex-wrap: wrap;
        }
        .brand { font-weight: 700; color: var(--ink); font-size: 1.15rem; }
        .nav { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; font-size: 0.94rem; }
        .nav form { margin: 0; }
        main { max-width: 1120px; margin: 2rem auto; padding: 0 1rem 3rem; }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 8px 24px rgba(43, 33, 28, 0.04);
            margin-bottom: 1.25rem;
        }
        h1 { margin: 0 0 0.5rem; font-size: 1.6rem; }
        h2 { margin: 0 0 0.75rem; font-size: 1.25rem; }
        .muted { color: var(--muted); }
        label { display: block; font-weight: 600; margin: 0.9rem 0 0.35rem; }
        input, select, textarea {
            width: 100%;
            padding: 0.65rem 0.8rem;
            border: 1px solid var(--line);
            border-radius: 10px;
            font-size: 0.96rem;
            background: #fff;
            color: var(--ink);
            font-family: inherit;
        }
        input[type="checkbox"], input[type="radio"] { width: auto; }
        button, .btn {
            display: inline-block;
            border: 0;
            border-radius: 999px;
            padding: 0.65rem 1.15rem;
            background: var(--accent);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.92rem;
        }
        button:hover, .btn:hover { background: var(--accent-dark); text-decoration: none; }
        .btn-ghost { background: transparent; color: var(--ink); border: 1px solid var(--line); }
        .btn-ghost:hover { background: #f5eee9; color: var(--ink); }
        .btn-sm { padding: 0.4rem 0.85rem; font-size: 0.84rem; }
        .error { color: var(--danger); font-size: 0.92rem; margin: 0.35rem 0 0; }
        .flash-error {
            background: #fde7e5;
            color: var(--danger);
            padding: 0.75rem 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        .stack { display: grid; gap: 0.4rem; }
        .flash-message { margin-bottom: 1rem; overflow-wrap: anywhere; }
        .flash-success { padding: .85rem 1rem; border: 1px solid #b7dfc8; border-radius: 10px; background: #e5f4eb; color: #236542; }
        select:focus-visible, textarea:focus-visible, input:focus-visible { outline: 3px solid var(--accent-dark); outline-offset: 2px; }
        .meta { margin-top: 1rem; font-size: 0.95rem; }
        .grid-kpi { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
        .kpi-box { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 1.15rem; }
        .kpi-value { font-size: 1.45rem; font-weight: 700; color: var(--accent-dark); margin-top: 0.25rem; }
        .table-wrap { overflow-x: auto; }
        table.data-table { width: 100%; border-collapse: collapse; font-size: 0.93rem; }
        table.data-table th, table.data-table td { padding: 0.7rem 0.65rem; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
        table.data-table th { font-weight: 700; color: var(--muted); background: #fcf9f7; }
        .badge { display: inline-block; padding: 0.2rem 0.65rem; border-radius: 999px; font-size: 0.78rem; font-weight: 600; background: #f2ebe6; color: var(--ink); }
        .badge-ok { background: #e5f4eb; color: var(--ok); }
        .badge-warn { background: #fef0c7; color: var(--warn); }
        .badge-danger { background: #fde7e5; color: var(--danger); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.85rem; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/beautypals.css') }}">
    @stack('styles')
</head>
<body>
    <header class="app-header">
        <a class="brand" href="{{ route('home') }}">Beauty<span>Pals.</span></a>
        <nav class="nav" aria-label="Điều hướng chính">
            <a href="{{ route('catalog.index') }}">Sản phẩm</a>
            @auth
                <span>{{ auth()->user()->name }} ({{ auth()->user()->role }})</span>
                <a href="{{ auth()->user()->homePath() }}">Khu vực của tôi</a>
                @if (auth()->user()->isCustomer())
                    <a href="{{ route('customer.cart.index') }}">Giỏ hàng</a>
                    <a href="{{ route('customer.orders.index') }}">Đơn của tôi</a>
                    <a href="{{ route('customer.addresses.index') }}">Địa chỉ</a>
                @endif
                @if (auth()->user()->isApprovedVendor())
                    <a href="{{ route('vendor.products.index') }}" @if (request()->routeIs('vendor.products.*')) aria-current="page" @endif>Sản phẩm của tôi</a>
                    <a href="{{ route('vendor.inventory.index') }}" @if (request()->routeIs('vendor.inventory.*')) aria-current="page" @endif>Kho & Lô</a>
                    <a href="{{ route('vendor.orders.index') }}" @if (request()->routeIs('vendor.orders.*')) aria-current="page" @endif>Đơn hàng</a>
                    <a href="{{ route('vendor.settlements.index') }}" @if (request()->routeIs('vendor.settlements.*')) aria-current="page" @endif>Đối soát</a>
                @endif
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.vendors.index') }}" @if (request()->routeIs('admin.vendors.*')) aria-current="page" @endif>Duyệt cửa hàng</a>
                    <a href="{{ route('admin.products.index') }}" @if (request()->routeIs('admin.products.*')) aria-current="page" @endif>Duyệt sản phẩm</a>
                    <a href="{{ route('admin.categories.index') }}" @if (request()->routeIs('admin.categories.*')) aria-current="page" @endif>Danh mục</a>
                    <a href="{{ route('admin.brands.index') }}" @if (request()->routeIs('admin.brands.*')) aria-current="page" @endif>Thương hiệu</a>
                    <a class="btn btn-sm" href="{{ route('admin.ingredients.index') }}" @if (request()->routeIs('admin.ingredients.*')) aria-current="page" @endif>Quản lý thành phần</a>
                    <a href="{{ route('admin.orders.index') }}" @if (request()->routeIs('admin.orders.*')) aria-current="page" @endif>Đơn toàn sàn</a>
                    <a href="{{ route('admin.commissions.index') }}" @if (request()->routeIs('admin.commissions.*')) aria-current="page" @endif>Hoa hồng</a>
                    <a href="{{ route('admin.settlements.index') }}" @if (request()->routeIs('admin.settlements.*')) aria-current="page" @endif>Đối soát</a>
                    <a href="{{ route('admin.users.index') }}" @if (request()->routeIs('admin.users.*')) aria-current="page" @endif>Người dùng</a>
                    <a href="{{ route('admin.audit-logs.index') }}" @if (request()->routeIs('admin.audit-logs.*')) aria-current="page" @endif>Audit Log</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-ghost btn-sm">Đăng xuất</button>
                </form>
            @else
                <a href="{{ route('login') }}">Đăng nhập</a>
                <a href="{{ route('register.vendor') }}">Mở gian hàng</a>
                <a class="btn btn-sm" href="{{ route('register') }}">Đăng ký</a>
            @endauth
        </nav>
    </header>
    <main>
        @include('partials.flash')
        @yield('content')
    </main>
    @include('partials.footer')
    <script src="{{ asset('js/password-toggle.js') }}" defer></script>
    <script src="{{ asset('js/login-countdown.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
