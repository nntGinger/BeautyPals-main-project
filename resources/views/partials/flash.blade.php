@if (session('status') || session('success') || session('error'))
    <div class="mx-auto mt-4 max-w-6xl px-4" role="alert">
        @if (session('error'))
            <div id="flash-error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ session('error') }}
            </div>
        @else
            <div id="flash-success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('success') ?? session('status') }}
            </div>
        @endif
    </div>
@endif
