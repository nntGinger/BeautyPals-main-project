@if (session('status') || session('success') || session('error'))
    <div class="flash-message" role="{{ session('error') ? 'alert' : 'status' }}">
        @if (session('error'))
            <div id="flash-error" class="flash-error">
                {{ session('error') }}
            </div>
        @else
            <div id="flash-success" class="flash-success">
                {{ session('success') ?? session('status') }}
            </div>
        @endif
    </div>
@endif
