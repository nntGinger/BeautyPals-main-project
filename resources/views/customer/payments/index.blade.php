@extends('layouts.app')

@section('title', 'Mô phỏng cổng thanh toán (S3-01)')

@section('content')
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
            <div>
                <h1>Mô phỏng Cổng thanh toán &amp; Callback (S3-01)</h1>
                <p class="muted" style="margin:0;">
                    Kiểm thử khởi tạo phiên thanh toán trực tuyến, callback có chữ ký HMAC-SHA256 và idempotency theo <code>txn_ref</code>. Luồng COD được giữ nguyên vẹn.
                </p>
            </div>
            <a class="btn-ghost btn" href="{{ route('customer.dashboard') }}">← Quay lại Dashboard</a>
        </div>

        @if ($orders->isEmpty())
            <p class="muted" style="margin-top:1.5rem;">Bạn chưa có đơn hàng nào để mô phỏng thanh toán.</p>
        @else
            <div style="overflow-x:auto;margin-top:1.25rem;">
                <table style="width:100%;border-collapse:collapse;font-size:0.95rem;">
                    <thead>
                        <tr style="border-bottom:2px solid var(--line);text-align:left;">
                            <th style="padding:0.7rem 0.5rem;">Mã đơn</th>
                            <th style="padding:0.7rem 0.5rem;">Tổng tiền</th>
                            <th style="padding:0.7rem 0.5rem;">Phương thức</th>
                            <th style="padding:0.7rem 0.5rem;">TT Thanh toán</th>
                            <th style="padding:0.7rem 0.5rem;">Giao dịch (Payments)</th>
                            <th style="padding:0.7rem 0.5rem;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr style="border-bottom:1px solid var(--line);vertical-align:top;">
                                <td style="padding:0.75rem 0.5rem;font-weight:600;">{{ $order->order_code }}</td>
                                <td style="padding:0.75rem 0.5rem;">{{ number_format($order->grand_total, 0, ',', '.') }} đ</td>
                                <td style="padding:0.75rem 0.5rem;">{{ strtoupper($order->payment_method) }}</td>
                                <td style="padding:0.75rem 0.5rem;">
                                    <span style="font-weight:600;color:{{ $order->payment_status === 'paid' ? 'var(--ok)' : ($order->payment_status === 'failed' ? 'var(--danger)' : 'var(--muted)') }};">
                                        {{ strtoupper($order->payment_status) }}
                                    </span>
                                </td>
                                <td style="padding:0.75rem 0.5rem;">
                                    @forelse ($order->payments as $payment)
                                        <div style="margin-bottom:0.35rem;">
                                            <code>{{ $payment->txn_ref }}</code>
                                            ({{ strtoupper($payment->method) }} — <strong>{{ $payment->status }}</strong>)
                                            @if ($payment->method !== 'cod')
                                                <a href="{{ route('customer.payments.gateway', $payment) }}" style="margin-left:0.35rem;">Mở cổng</a>
                                            @endif
                                        </div>
                                    @empty
                                        <span class="muted">Chưa có bản ghi payment</span>
                                    @endforelse
                                </td>
                                <td style="padding:0.75rem 0.5rem;">
                                    @if ($order->status === 'cancelled')
                                        <span class="muted">Đơn đã hủy</span>
                                    @elseif ($order->payment_status === 'paid')
                                        <span class="muted">Đã hoàn tất</span>
                                    @elseif ($order->payment_method === 'cod' && $order->status !== 'pending')
                                        <span class="muted">COD đang giao</span>
                                    @else
                                        <form method="POST" action="{{ route('customer.payments.initiate', $order) }}" style="display:flex;gap:0.4rem;align-items:center;flex-wrap:wrap;">
                                            @csrf
                                            <select name="method" style="padding:0.4rem 0.6rem;border:1px solid var(--line);border-radius:8px;">
                                                @foreach ($gatewayMethods as $method)
                                                    <option value="{{ $method }}" @selected($order->payment_method === $method)>{{ strtoupper($method) }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" style="padding:0.45rem 0.85rem;font-size:0.88rem;">Tạo phiên / Mở cổng</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($orders->hasPages())
                <div style="margin-top:1rem;display:flex;justify-content:space-between;align-items:center;gap:0.75rem;">
                    @if ($orders->onFirstPage())
                        <span class="btn btn-ghost" style="opacity:0.5;cursor:default;">« Trang trước</span>
                    @else
                        <a class="btn btn-ghost" href="{{ $orders->previousPageUrl() }}">« Trang trước</a>
                    @endif
                    <span class="muted">Trang {{ $orders->currentPage() }} / {{ $orders->lastPage() }}</span>
                    @if ($orders->hasMorePages())
                        <a class="btn btn-ghost" href="{{ $orders->nextPageUrl() }}">Trang sau »</a>
                    @else
                        <span class="btn btn-ghost" style="opacity:0.5;cursor:default;">Trang sau »</span>
                    @endif
                </div>
            @endif
        @endif
    </div>
@endsection
