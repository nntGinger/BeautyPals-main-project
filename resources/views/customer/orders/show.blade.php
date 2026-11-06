@extends('layouts.app')

@section('title', 'Chi tiết đơn hàng ' . $order->order_code)

@section('content')
    <div style="margin-bottom: 1rem;">
        <a href="{{ route('customer.orders.index') }}">&larr; Quay lại danh sách đơn hàng</a>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1>Đơn hàng {{ $order->order_code }}</h1>
                <p class="muted" style="margin: 0.25rem 0;">
                    Người nhận: <strong>{{ $order->receiver }}</strong> ({{ $order->phone }})<br>
                    Địa chỉ: {{ $order->address_text }}
                </p>
            </div>
            <div style="text-align: right;">
                <span class="badge">Trạng thái đơn cha: {{ strtoupper($order->status) }}</span>
                <p style="font-size: 1.3rem; font-weight: 700; color: var(--accent-dark); margin: 0.4rem 0 0;">
                    Tổng: {{ number_format($order->grand_total, 0, ',', '.') }} ₫
                </p>
            </div>
        </div>
        @error('vendor_order') <p class="error">{{ $message }}</p> @enderror
    </div>

    @foreach ($order->vendorOrders as $vo)
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem;">
                <div>
                    <h2 style="margin: 0;">Kiện hàng {{ $vo->vo_code }} — {{ $vo->vendor?->store_name }}</h2>
                    <p class="muted" style="margin: 0.25rem 0 0;">
                        Trạng thái: <span class="badge">{{ strtoupper($vo->status) }}</span>
                        @if ($vo->tracking_code)
                            · Mã vận đơn: <code>{{ $vo->tracking_code }}</code>
                        @endif
                    </p>
                </div>

                @if ($vo->can_cancel)
                    <form method="POST" action="{{ route('customer.orders.cancel', $vo) }}" style="display: flex; gap: 0.4rem; align-items: center;">
                        @csrf
                        <label for="cancel-reason-{{ $vo->id }}" hidden>Lý do hủy</label>
                        <input id="cancel-reason-{{ $vo->id }}" type="text" name="cancel_reason" placeholder="Lý do hủy đơn..." value="Đổi ý đặt hàng" style="width: 180px; padding: 0.4rem;">
                        <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--danger);">Hủy kiện hàng này</button>
                    </form>
                @endif
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sản phẩm</th>
                            <th>Mã SKU</th>
                            <th>Đơn giá</th>
                            <th>SL</th>
                            <th>Thành tiền</th>
                            <th>Phân bổ lô FEFO</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vo->items as $item)
                            <tr>
                                <td><strong>{{ $item->product_name }}</strong></td>
                                <td><code>{{ $item->sku_code }}</code></td>
                                <td>{{ number_format($item->unit_price, 0, ',', '.') }} ₫</td>
                                <td>{{ $item->qty }}</td>
                                <td><strong>{{ number_format($item->line_total, 0, ',', '.') }} ₫</strong></td>
                                <td>
                                    @foreach ($item->allocations as $alloc)
                                        <div>
                                            <span class="badge">{{ $alloc->batch?->batch_no }} ({{ $alloc->qty }} · {{ $alloc->status }})</span>
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 1rem;">
                <strong>Lịch sử chuyển trạng thái (Timeline):</strong>
                <ul style="margin: 0.4rem 0 0; padding-left: 1.2rem; font-size: 0.9rem;" class="muted">
                    @foreach ($vo->statusHistories as $hist)
                        <li>
                            [{{ $hist->created_at }}]
                            <code>{{ $hist->from_status ?? 'khởi tạo' }}</code> &rarr; <strong><code>{{ $hist->to_status }}</code></strong>
                            @if ($hist->note) — {{ $hist->note }} @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endforeach
@endsection

