@if (session('success'))
    <div style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:10px;padding:.8rem 1rem;margin-bottom:1rem;color:#065f46;">
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;padding:.8rem 1rem;margin-bottom:1rem;color:#991b1b;">
        {{ session('error') }}
    </div>
@endif

@if ($errors->any())
    <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;padding:.8rem 1rem;margin-bottom:1rem;color:#991b1b;">
        <ul style="margin:0;padding-left:1.2rem;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

