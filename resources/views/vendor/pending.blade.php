@extends('layouts.app')

@section('title', 'Trạng thái cửa hàng – BeautyPals')

@section('content')
<div class="card" style="max-width:580px;margin:0 auto;">

    @php
        $status = $vendor?->status ?? 'no_vendor';
        $icons  = ['pending' => '⏳', 'rejected' => '❌', 'suspended' => '🔒', 'no_vendor' => '⚠️'];
        $titles = [
            'pending'   => 'Hồ sơ đang chờ xét duyệt',
            'rejected'  => 'Hồ sơ bị từ chối',
            'suspended' => 'Cửa hàng bị tạm khóa',
            'no_vendor' => 'Chưa có hồ sơ cửa hàng',
        ];
    @endphp

    <h1>{{ $icons[$status] ?? '⚠️' }} {{ $titles[$status] ?? 'Chưa có hồ sơ cửa hàng' }}</h1>

    @include('partials.flash')

    @if($status === 'pending')
        <p>Hồ sơ của bạn đang được Admin xem xét. Chúng tôi sẽ thông báo qua email khi có kết quả.</p>

    @elseif($status === 'rejected')
        <p><strong>Lý do:</strong> {{ $vendor->reject_reason }}</p>
        <p class="muted">Bạn có thể chỉnh sửa hồ sơ và nộp lại để được xem xét lại.</p>

        <form method="POST" action="{{ route('vendor.resubmit') }}" enctype="multipart/form-data"
              style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--line);">
            @csrf
            <label for="license_file">Thay thế giấy phép (tùy chọn)</label>
            <input id="license_file" type="file" name="license_file" accept=".pdf,.jpg,.jpeg,.png"
                   style="padding:.4rem .2rem;">
            @error('license_file') <p class="error">{{ $message }}</p> @enderror
            <button type="submit" style="margin-top:.8rem;">Nộp lại hồ sơ</button>
        </form>

    @elseif($status === 'suspended')
        <p><strong>Lý do:</strong> {{ $vendor->suspended_reason }}</p>
        <p class="muted">Vui lòng liên hệ hỗ trợ để được tư vấn thêm.</p>

    @else
        <p>Tài khoản của bạn chưa có hồ sơ cửa hàng.
            <a href="{{ route('vendor.register.form') }}">Đăng ký ngay</a>.
        </p>
    @endif

    <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--line);">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" style="background:var(--danger);">Đăng xuất</button>
        </form>
    </div>
</div>
@endsection
