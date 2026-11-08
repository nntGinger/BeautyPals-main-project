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

@if ($errors->any())
    <div class="flash-error" role="alert">
        <ul style="margin:0;padding-left:1.2rem;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
