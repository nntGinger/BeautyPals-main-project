<header class="sticky top-0 z-40 border-b border-brand-100 bg-white/80 backdrop-blur">
    <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3" aria-label="Điều hướng chính">
        <a href="{{ url('/') }}" id="nav-brand" class="text-xl font-semibold tracking-tight text-brand-600">
            Beauty<span class="text-ink">Pals</span>
        </a>

        <div class="flex items-center gap-3 text-sm font-medium">
            @auth
                <span id="nav-username" class="hidden text-ink/70 sm:inline">Xin chào, {{ auth()->user()->name }}</span>
                @if (Route::has('logout'))
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" id="nav-logout"
                            class="rounded-full border border-brand-500 px-4 py-1.5 text-brand-600 transition hover:bg-brand-50">
                            Đăng xuất
                        </button>
                    </form>
                @endif
            @else
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" id="nav-login"
                        class="rounded-full px-4 py-1.5 text-ink/80 transition hover:text-brand-600">Đăng nhập</a>
                @endif
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" id="nav-register"
                        class="rounded-full bg-brand-500 px-4 py-1.5 text-white shadow-sm transition hover:bg-brand-600">Đăng ký</a>
                @endif
            @endauth
        </div>
    </nav>
</header>
