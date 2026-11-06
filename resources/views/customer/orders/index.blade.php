@extends('layouts.app')

@section('title', 'Đơn hàng của tôi — BeautyPals')

@section('content')
    <div class="card">
        <h1>Đơn hàng của tôi</h1>
        <p class="muted">Theo dõi trạng thái đơn hàng tổng và từng kiện hàng theo gian hàng (Vendor).</p>
    </div>

    @if ($orders->isEmpty())
        <div class="card" style="text-align: center; padding: 2.5rem 1.5rem;">
            <h2>Bạn chưa có đơn hàng nào</h2>
            <a href="{{ route('catalog.index') }}" class="btn" style="margin-top: 0.75rem;">Mua sắm ngay</a>
        </div>
    @else
        @foreach ($orders as $order)
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                    <div>
                        <strong>Mã đơn tổng: {{ $order->order_code }}</strong>
                        <span class="muted">· Đặt lúc {{ $order->placed_at?->format('d/m/Y H:i') }}</span>
                    </div>
                    <div>
                        <span class="badge">{{ strtoupper($order->status) }}</span>
                        <a href="{{ route('customer.orders.show', $order) }}" class="btn btn-sm" style="margin-left: 0.5rem;">Xem chi tiết & Timeline</a>
                    </div>
                </div>

                <p class="muted" style="margin: 0.5rem 0;">
                    Giao đến: <strong>{{ $order->receiver }}</strong> ({{ $order->phone }}) — {{ $order->address_text }}
                </p>

                <p style="margin: 0.5rem 0 0;">
                    Tổng thanh toán COD: <strong style="color: var(--accent-dark);">{{ number_format($order->grand_total, 0, ',', '.') }} ₫</strong>
                    ({{ $order->vendorOrders->count() }} kiện hàng vendor)
                </p>
            </div>
        @endforeach

        {{ $orders->links() }}
    @endif
@endsection

