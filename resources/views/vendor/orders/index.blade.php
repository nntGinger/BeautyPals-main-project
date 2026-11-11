@extends('layouts.app')

@section('title', 'Đơn hàng cửa hàng')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/vendor-operations.css') }}">
@endpush

@section('content')
    <div class="operations-heading">
        <div><p class="eyebrow">Quản lý cửa hàng</p><h1>Đơn hàng</h1><p class="muted">Theo dõi và xử lý đơn của {{ auth()->user()->vendor->store_name }}.</p></div>
        <a class="btn btn-ghost" href="{{ route('vendor.inventory.index') }}">Quản lý tồn kho</a>
    </div>
    <section class="operations-panel">
        <form method="GET" action="{{ route('vendor.orders.index') }}" class="operations-filter">
            <div><label for="status">Trạng thái đơn</label><select id="status" name="status"><option value="">Tất cả trạng thái</option>@foreach ($statuses as $option)<option value="{{ $option->value }}" @selected($status === $option->value)>{{ $option->label() }}</option>@endforeach</select></div>
            <button type="submit">Lọc đơn hàng</button>
            <a href="{{ route('vendor.orders.index') }}">Xóa bộ lọc</a>
        </form>
        @error('status')<p class="error" role="alert">{{ $message }}</p>@enderror
        <div class="operations-table-wrap">
            <table class="operations-table">
                <caption>{{ $orders->total() }} đơn hàng · 15 đơn mỗi trang</caption>
                <thead><tr><th>Mã đơn / Ngày đặt</th><th>Người nhận</th><th>Số mặt hàng</th><th>Tổng tiền</th><th>Trạng thái</th><th>Chi tiết</th></tr></thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td><a href="{{ route('vendor.orders.show', $order) }}"><strong>{{ $order->vo_code }}</strong></a><small>{{ $order->order->placed_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}</small></td>
                            <td>{{ $order->order->receiver }}</td><td>{{ $order->items_count }}</td><td class="money">{{ number_format($order->total, 0, ',', '.') }} đ</td>
                            <td><x-order-status :status="$order->status" /></td>
                            <td><a href="{{ route('vendor.orders.show', $order) }}" aria-label="Xem đơn {{ $order->vo_code }}">Xem đơn →</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="operations-empty">Chưa có đơn hàng phù hợp với bộ lọc.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('vendor.partials.pagination', ['paginator' => $orders])
    </section>
@endsection
