<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 — Không có quyền truy cập</title>
    <style>
        body { font-family: "Segoe UI", system-ui, sans-serif; background: #faf6f3; color: #2b211c; margin: 0; }
        main { max-width: 640px; margin: 12vh auto; padding: 0 1rem; }
        .card { background: #fff; border: 1px solid #eadfd8; border-radius: 16px; padding: 2rem; }
        h1 { margin-top: 0; color: #a34452; }
        .muted { color: #7a6a63; }
        a { color: #a34452; }
        .btn { display: inline-block; margin-top: 1rem; background: #c45c6a; color: #fff; text-decoration: none; padding: 0.7rem 1.2rem; border-radius: 999px; }
    </style>
</head>
<body>
    <main>
        <div class="card">
            <p class="muted">HTTP 403</p>
            <h1>Bạn không được phép vào trang này</h1>
            <p>
                {{ $exception?->getMessage() ?: 'Khu vực này dành cho vai trò khác, hoặc cửa hàng của bạn chưa được duyệt.' }}
            </p>
            <p class="muted">Bạn có thể quay về khu vực của mình để tiếp tục sử dụng BeautyPals.</p>
            @auth
                <a class="btn" href="{{ auth()->user()->homePath() }}">Về khu vực của tôi</a>
            @else
                <a class="btn" href="{{ url('/') }}">Về trang chủ</a>
            @endauth
        </div>
    </main>
</body>
</html>
