@extends('layouts.app')

@section('title', 'Lô hàng '.$batch->batch_no)
@push('styles')<link rel="stylesheet" href="{{ asset('css/vendor-operations.css') }}">@endpush

@section('content')
    <a href="{{ route('vendor.inventory.index') }}">← Danh sách lô hàng</a>
    <div class="operations-heading"><div><p class="eyebrow">Chi tiết lô hàng</p><h1>{{ $batch->batch_no }}</h1><p class="muted">{{ $batch->sku->product->name }} · {{ $batch->sku->sku_code }}</p></div><span class="order-status">{{ $batch->status === 'active' ? 'Đang hoạt động' : ($batch->status === 'expired' ? 'Hết hạn' : $batch->status) }}</span></div>
    <div class="inventory-stats"><div><small>Tồn thực tế</small><strong>{{ $batch->qty_on_hand }}</strong></div><div><small>Đang giữ chỗ</small><strong>{{ $batch->qty_reserved }}</strong></div><div><small>Hạn sử dụng</small><strong>{{ $batch->exp_date->format('d/m/Y') }}</strong></div></div>
    <section class="operations-panel">
        <h2>Điều chỉnh tồn kho</h2><p class="muted">Nhập số dương để tăng, số âm để giảm. Tồn sau điều chỉnh phải từ {{ $batch->qty_reserved }} trở lên để bảo vệ hàng đã giữ chỗ.</p>
        <form method="POST" action="{{ route('vendor.inventory.adjust', $batch) }}" class="inventory-adjust">
            @csrf
            <label for="delta">Số lượng điều chỉnh (ví dụ: 5 hoặc -3)</label><input id="delta" type="number" name="delta" step="1" min="-2147483648" max="2147483647" value="{{ old('delta') }}" required>
            @error('delta')<p class="error" role="alert">{{ $message }}</p>@enderror
            <label for="reason">Lý do điều chỉnh (bắt buộc)</label><textarea id="reason" name="reason" rows="3" maxlength="500" required placeholder="Ví dụ: Kiểm kê phát hiện 2 sản phẩm hư hỏng">{{ old('reason') }}</textarea>
            @error('reason')<p class="error" role="alert">{{ $message }}</p>@enderror
            <button type="submit">Lưu điều chỉnh</button>
        </form>
    </section>
    <section class="operations-panel"><h2>Lịch sử biến động kho</h2><div class="operations-table-wrap"><table class="operations-table"><thead><tr><th>Thời điểm</th><th>Loại</th><th>Thay đổi</th><th>Lý do</th><th>Người thực hiện</th></tr></thead><tbody>
        @forelse ($movements as $movement)
            <tr><td>{{ $movement->created_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s') }}</td><td>{{ match ($movement->type) { 'adjust' => 'Điều chỉnh', 'reserve' => 'Giữ chỗ', 'commit' => 'Xuất kho', 'release' => 'Nhả giữ chỗ', 'inbound_purchase' => 'Nhập kho', default => $movement->type } }}</td><td><strong>{{ $movement->qty > 0 ? '+' : '' }}{{ $movement->qty }}</strong></td><td>{{ $movement->reason ?? '—' }}</td><td>{{ $movement->creator?->name ?? 'Hệ thống' }}</td></tr>
        @empty<tr><td colspan="5" class="operations-empty">Chưa có biến động kho.</td></tr>@endforelse
        </tbody></table></div>@include('vendor.partials.pagination', ['paginator' => $movements])
    </section>
@endsection
