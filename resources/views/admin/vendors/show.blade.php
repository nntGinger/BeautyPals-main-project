@extends('layouts.app')

@section('title', 'Chi tiết gian hàng ' . $vendor->store_name)

@section('content')
    <div style="margin-bottom: 1rem;">
        <a href="{{ route('admin.vendors.index') }}">&larr; Quay lại danh sách gian hàng</a>
    </div>

    <div class="card">
        <h1>{{ $vendor->store_name }}</h1>
        <p class="muted">
            Trạng thái hiện tại: <span class="badge">{{ strtoupper($vendor->status) }}</span>
            · Số sản phẩm: <strong>{{ $vendor->products_count }}</strong>
        </p>

        <ul>
            <li>Chủ gian hàng: <strong>{{ $vendor->owner?->name }}</strong> ({{ $vendor->owner?->email }})</li>
            <li>Số điện thoại: {{ $vendor->phone ?? '—' }}</li>
            <li>Mã số thuế: <code>{{ $vendor->tax_code }}</code></li>
            <li>Địa chỉ: {{ $vendor->address ?? '—' }}</li>
            <li>Giấy phép kinh doanh: <code>{{ $vendor->license_file }}</code>
                (<a href="{{ route('admin.vendors.license', $vendor) }}">Tải xuống giấy phép</a>)
            </li>
            @if ($vendor->reject_reason)
                <li>Lý do từ chối: <strong style="color: var(--danger);">{{ $vendor->reject_reason }}</strong></li>
            @endif
            @if ($vendor->suspended_reason)
                <li>Lý do tạm khóa: <strong style="color: var(--danger);">{{ $vendor->suspended_reason }}</strong></li>
            @endif
        </ul>

        @error('reason') <p class="error">{{ $message }}</p> @enderror

        <hr style="border: 0; border-top: 1px solid var(--line); margin: 1.5rem 0;">

        <h2>Hành động kiểm duyệt</h2>
        <div class="form-grid">
            @if ($vendor->status === 'pending' || $vendor->status === 'rejected')
                <form method="POST" action="{{ route('admin.vendors.approve', $vendor) }}">
                    @csrf
                    <button type="submit">Duyệt cửa hàng (Approve)</button>
                </form>
            @endif

            @if ($vendor->status === 'pending')
                <form method="POST" action="{{ route('admin.vendors.reject', $vendor) }}">
                    @csrf
                    <label for="reject-reason">Lý do từ chối (bắt buộc)</label>
                    <input id="reject-reason" type="text" name="reason" required placeholder="Nhập lý do từ chối...">
                    <button type="submit" class="btn btn-ghost" style="margin-top: 0.5rem; color: var(--danger);">Từ chối hồ sơ</button>
                </form>
            @endif

            @if ($vendor->status === 'approved')
                <form method="POST" action="{{ route('admin.vendors.suspend', $vendor) }}">
                    @csrf
                    <label for="suspend-reason">Lý do tạm khóa (bắt buộc)</label>
                    <input id="suspend-reason" type="text" name="reason" required placeholder="Nhập lý do khóa gian hàng...">
                    <button type="submit" class="btn btn-ghost" style="margin-top: 0.5rem; color: var(--danger);">Tạm khóa gian hàng</button>
                </form>
            @endif

            @if ($vendor->status === 'suspended')
                <form method="POST" action="{{ route('admin.vendors.restore', $vendor) }}">
                    @csrf
                    <button type="submit">Mở khóa gian hàng (Restore)</button>
                </form>
            @endif
        </div>
    </div>
@endsection

