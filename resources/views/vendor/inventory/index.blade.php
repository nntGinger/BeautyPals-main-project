@extends('layouts.app')

@section('title', 'Tồn kho cửa hàng')
@push('styles')<link rel="stylesheet" href="{{ asset('css/vendor-operations.css') }}">@endpush

@section('content')
    <div class="operations-heading"><div><p class="eyebrow">Quản lý cửa hàng</p><h1>Tồn kho theo lô</h1><p class="muted">Kiểm tra số lượng thực tế, giữ chỗ và lịch sử điều chỉnh.</p></div><a class="btn btn-ghost" href="{{ route('vendor.orders.index') }}">Đơn hàng</a></div>
    <section class="operations-panel"><div class="operations-table-wrap"><table class="operations-table">
        <caption>{{ $batches->total() }} lô hàng · Sắp xếp theo hạn sử dụng gần nhất</caption>
        <thead><tr><th>Lô / Sản phẩm</th><th>Hạn sử dụng</th><th>Thực tế</th><th>Giữ chỗ</th><th>Có thể bán</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
        @forelse ($batches as $batch)
            <tr><td><strong>{{ $batch->batch_no }}</strong><small>{{ $batch->sku->product->name }}</small><small>{{ $batch->sku->sku_code }}</small></td><td>{{ $batch->exp_date->format('d/m/Y') }}</td><td>{{ $batch->qty_on_hand }}</td><td>{{ $batch->qty_reserved }}</td><td>{{ $batch->status === 'active' && $batch->exp_date->toDateString() > today('Asia/Ho_Chi_Minh')->toDateString() ? max(0, $batch->qty_on_hand - $batch->qty_reserved) : 0 }}</td><td>{{ $batch->status === 'active' ? 'Đang hoạt động' : ($batch->status === 'expired' ? 'Hết hạn' : $batch->status) }}</td><td><a href="{{ route('vendor.inventory.show', $batch) }}">Xem / Điều chỉnh</a></td></tr>
        @empty<tr><td colspan="7" class="operations-empty">Cửa hàng chưa có lô hàng.</td></tr>@endforelse
        </tbody></table></div>@include('vendor.partials.pagination', ['paginator' => $batches])
    </section>
@endsection
