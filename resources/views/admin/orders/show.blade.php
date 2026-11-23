@extends('layouts.app')

@section('title', 'Chi tiết kiện hàng ' . $vendorOrder->vo_code)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/vendor-operations.css') }}">
@endpush

@section('content')
    <div class="operations-heading">
        <div>
            <h1>Kiện hàng {{ $vendorOrder->vo_code }}</h1>
            <p class="muted">
                Đơn cha: <code>{{ $vendorOrder->order?->order_code }}</code>
                · Gian hàng: <strong>{{ $vendorOrder->vendor?->store_name }}</strong>
                · Trạng thái: <x-order-status :status="$vendorOrder->status" />
            </p>
        </div>
        <a class="btn btn-ghost" href="{{ route('admin.orders.index') }}">← Danh sách đơn hàng</a>
    </div>

    <div class="operations-columns">
        <section class="operations-panel">
            <h2>Thông tin giao nhận &amp; Thanh toán</h2>
            <p>
                <strong>Người nhận:</strong> {{ $vendorOrder->order?->receiver }}<br>
                <strong>Số điện thoại:</strong> {{ $vendorOrder->order?->phone }}<br>
                <strong>Địa chỉ:</strong> {{ $vendorOrder->order?->address_text }}<br>
                <strong>Tài khoản đặt:</strong> {{ $vendorOrder->order?->customer?->email ?? '—' }}
            </p>
            <p>
                <strong>Phương thức:</strong> {{ strtoupper((string) ($vendorOrder->order?->payment_method ?? 'cod')) }}<br>
                <strong>Trạng thái thanh toán đơn cha:</strong> {{ $vendorOrder->order?->payment_status ?? 'pending' }}
            </p>
        </section>

        <section class="operations-panel">
            <h2>Tổng hợp tài chính kiện hàng</h2>
            <dl class="operations-totals">
                <dt>Tạm tính (Subtotal)</dt>
                <dd class="money">{{ number_format((int) $vendorOrder->subtotal, 0, ',', '.') }} ₫</dd>
                <dt>Phí vận chuyển</dt>
                <dd class="money">{{ number_format((int) $vendorOrder->shipping_fee, 0, ',', '.') }} ₫</dd>
                <dt>Giảm giá</dt>
                <dd class="money">-{{ number_format((int) $vendorOrder->discount, 0, ',', '.') }} ₫</dd>
                <dt>Tổng kiện hàng</dt>
                <dd class="money"><strong>{{ number_format((int) $vendorOrder->total, 0, ',', '.') }} ₫</strong></dd>
                <dt>Hoa hồng sàn (Captured)</dt>
                <dd class="money">{{ number_format((int) $vendorOrder->commission_amount, 0, ',', '.') }} ₫</dd>
            </dl>
        </section>
    </div>

    <section class="operations-panel">
        <h2>Chi tiết sản phẩm &amp; Phân bổ lô hàng (FEFO)</h2>
        <div class="operations-table-wrap">
            <table class="operations-table">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>SKU</th>
                        <th>Đơn giá</th>
                        <th>SL</th>
                        <th>Thành tiền</th>
                        <th>Hoa hồng đã chụp</th>
                        <th>Lô phân bổ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vendorOrder->items as $item)
                        <tr>
                            <td>{{ $item->product_name }}</td>
                            <td><code>{{ $item->sku_code }}</code></td>
                            <td class="money">{{ number_format((int) $item->unit_price, 0, ',', '.') }} ₫</td>
                            <td>{{ $item->qty }}</td>
                            <td class="money"><strong>{{ number_format((int) $item->line_total, 0, ',', '.') }} ₫</strong></td>
                            <td class="money">{{ number_format((int) $item->commission_amount, 0, ',', '.') }} ₫ ({{ $item->commission_rate }}%)</td>
                            <td>
                                @forelse ($item->allocations as $allocation)
                                    <div class="allocation">
                                        <code>{{ $allocation->batch?->batch_no ?? ('#' . $allocation->batch_id) }}</code>
                                        — SL: {{ $allocation->qty }} ({{ $allocation->status }})
                                    </div>
                                @empty
                                    <span class="muted">Chưa có phân bổ lô</span>
                                @endforelse
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="operations-empty">Không có dòng sản phẩm.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="operations-panel">
        <h2>Lịch sử chuyển trạng thái</h2>
        <ol class="order-timeline">
            @forelse ($vendorOrder->statusHistory as $history)
                <li>
                    <strong>{{ \App\Enums\OrderStatus::tryFrom($history->to_status)?->label() ?? $history->to_status }}</strong>
                    <small>
                        {{ $history->created_at?->format('d/m/Y H:i:s') }}
                        · Người thực hiện: {{ $history->changer?->name ?? 'Hệ thống' }}
                    </small>
                    @if ($history->note)
                        <p>{{ $history->note }}</p>
                    @endif
                </li>
            @empty
                <li><p class="muted">Chưa ghi nhận chuyển trạng thái.</p></li>
            @endforelse
        </ol>
    </section>
@endsection

