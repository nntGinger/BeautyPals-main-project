@extends('layouts.app')

@section('title', 'Chi tiết đơn hàng - BeautyPals')

@push('styles')
<style>
    .bp-detail {
        display: grid;
        gap: 22px;
    }

    .bp-detail__back {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 600;
        color: #a34d61;
        margin-bottom: 4px;
    }

    .bp-detail__heading {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex-wrap: wrap;
    }

    .bp-detail__heading h1 {
        margin: 0 0 8px;
        font-size: clamp(24px, 4vw, 32px);
        color: #39272f;
    }

    .bp-detail__subtitle {
        color: #826e78;
        font-size: 14px;
        line-height: 1.6;
    }

    .bp-detail__badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 700;
        background: #fce9ee;
        color: #a34d61;
    }

    .bp-detail__badge--success {
        background: #e4f5e9;
        color: #257547;
    }

    .bp-detail__badge--danger {
        background: #fee8e8;
        color: #b33d45;
    }

    .bp-detail__panel {
        background: #fff;
        border: 1px solid #f0dfe4;
        border-radius: 20px;
        padding: 22px;
        box-shadow: 0 10px 32px rgba(99, 51, 68, .05);
    }

    .bp-detail__panel h2 {
        font-size: 18px;
        color: #39272f;
        margin: 0 0 18px;
    }

    .bp-detail__columns {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
    }

    .bp-detail__info {
        display: grid;
        gap: 14px;
    }

    .bp-detail__label {
        color: #907d85;
        font-size: 13px;
        margin-bottom: 4px;
    }

    .bp-detail__value {
        font-weight: 600;
        color: #403039;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .bp-detail__vendor-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        padding-bottom: 16px;
        border-bottom: 1px solid #f3e6e9;
    }

    .bp-detail__vendor-name {
        font-size: 17px;
        font-weight: 700;
        color: #39272f;
    }

    .bp-detail__vendor-code {
        color: #907d85;
        font-size: 13px;
        margin-top: 5px;
    }

    .bp-detail__items {
        display: grid;
        gap: 0;
    }

    .bp-detail__item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        padding: 16px 0;
        border-bottom: 1px solid #f5eaed;
    }

    .bp-detail__item-name {
        font-weight: 700;
        margin-bottom: 6px;
        overflow-wrap: anywhere;
    }

    .bp-detail__item-meta {
        color: #907d85;
        font-size: 13px;
        line-height: 1.7;
    }

    .bp-detail__price {
        font-weight: 700;
        color: #ae5268;
        white-space: nowrap;
        text-align: right;
    }

    .bp-detail__vendor-total {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding-top: 16px;
        font-weight: 700;
    }

    .bp-detail__timeline {
        margin: 14px 0 0 6px;
        padding: 0 0 0 25px;
        border-left: 2px solid #eed5dd;
        list-style: none;
    }

    .bp-detail__event {
        position: relative;
        padding: 0 0 22px 0;
    }

    .bp-detail__event:last-child {
        padding-bottom: 0;
    }

    .bp-detail__event::before {
        content: "";
        position: absolute;
        left: -33px;
        top: 3px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #c96882;
        border: 3px solid #fff;
        box-shadow: 0 0 0 2px #eed5dd;
    }

    .bp-detail__event-title {
        font-weight: 700;
        color: #47353e;
    }

    .bp-detail__event-time {
        color: #907d85;
        font-size: 12px;
        margin-top: 5px;
    }

    .bp-detail__event-note {
        color: #6d5b64;
        font-size: 13px;
        margin-top: 7px;
        line-height: 1.6;
    }

    .bp-detail__summary {
        display: grid;
        gap: 12px;
    }

    .bp-detail__summary-row {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        color: #796872;
    }

    .bp-detail__summary-row strong {
        color: #403039;
    }

    .bp-detail__summary-row--final {
        border-top: 1px solid #f0dfe4;
        padding-top: 15px;
        margin-top: 4px;
        font-size: 18px;
        font-weight: 700;
        color: #39272f;
    }

    .bp-detail__summary-row--final strong {
        color: #ad4d66;
    }

    .bp-detail__cancel-panel {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
    }

    .bp-detail__cancel-description {
        margin: 0;
        color: #6d5b64;
        font-size: 14px;
        line-height: 1.7;
    }

    .bp-detail__cancel-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #bc405a;
        border-radius: 12px;
        background: #bc405a;
        color: #fff;
        padding: 12px 20px;
        font: inherit;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
    }

    .bp-detail__cancel-button:hover:not(:disabled) {
        background: #a22f49;
    }

    .bp-detail__cancel-button:focus-visible {
        outline: 3px solid #f1adc0;
        outline-offset: 3px;
    }

    .bp-detail__cancel-button:disabled {
        opacity: .5;
        cursor: not-allowed;
    }

    .bp-detail__notice {
        border-radius: 12px;
        background: #fff7ed;
        border: 1px solid #f5dfbb;
        color: #79562f;
        padding: 13px 16px;
        font-size: 13px;
        line-height: 1.7;
    }

    @@media (max-width: 650px) {
        .bp-detail__columns {
            grid-template-columns: 1fr;
        }

        .bp-detail__panel {
            padding: 18px;
            border-radius: 16px;
        }

        .bp-detail__item {
            flex-direction: column;
            gap: 8px;
        }

        .bp-detail__price {
            text-align: left;
        }
    }
</style>
@endpush

@section('content')
@php
    $statusLabels = [
    'pending' => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận',
    'processing' => 'Đang xử lý',
    'packed' => 'Đã đóng gói',
    'shipped' => 'Đang giao hàng',
    'delivered' => 'Đã giao hàng',
    'completed' => 'Hoàn thành',
    'cancelled' => 'Đã hủy',
    'rejected' => 'Bị từ chối',
];

    $statusClasses = [
        'delivered' => 'bp-detail__badge--success',
        'completed' => 'bp-detail__badge--success',
        'cancelled' => 'bp-detail__badge--danger',
        'rejected' => 'bp-detail__badge--danger',
    ];

    $money = fn ($amount) => number_format((int) $amount, 0, ',', '.') . ' ₫';

    $orderStatus = $order->status ?? 'pending';
@endphp

<div class="bp-detail">

    <a class="bp-detail__back" href="{{ route('customer.orders.index') }}">
        ← Quay lại đơn hàng của tôi
    </a>

    <div class="bp-detail__heading">
        <div>
            <h1>Chi tiết đơn hàng</h1>

            <div class="bp-detail__subtitle">
                Mã đơn hàng: <strong>{{ $order->order_code }}</strong>

                @if ($order->placed_at)
                    <div>
                        Ngày đặt:
                        {{ $order->placed_at->format('d/m/Y H:i') }}
                    </div>
                @endif
            </div>
        </div>

        <span class="bp-detail__badge {{ $statusClasses[$orderStatus] ?? '' }}">
            {{ $statusLabels[$orderStatus] ?? 'Chưa xác định' }}
        </span>
    </div>

    <div class="bp-detail__columns">
        <section class="bp-detail__panel">
            <h2>Thông tin nhận hàng</h2>

            <div class="bp-detail__info">
                <div>
                    <div class="bp-detail__label">Người nhận</div>
                    <div class="bp-detail__value">{{ $order->receiver }}</div>
                </div>

                <div>
                    <div class="bp-detail__label">Số điện thoại</div>
                    <div class="bp-detail__value">{{ $order->phone }}</div>
                </div>

                <div>
                    <div class="bp-detail__label">Địa chỉ giao hàng</div>
                    <div class="bp-detail__value">
                        {{ $order->address_text }}
                    </div>
                </div>
            </div>
        </section>

        <section class="bp-detail__panel">
            <h2>Thông tin thanh toán</h2>

            <div class="bp-detail__info">
                <div>
                    <div class="bp-detail__label">Phương thức</div>
                    <div class="bp-detail__value">
                        {{ strtoupper((string) $order->payment_method) === 'COD'
                            ? 'Thanh toán khi nhận hàng (COD)'
                            : ($order->payment_method ?: 'Chưa xác định') }}
                    </div>
                </div>

                <div>
                    <div class="bp-detail__label">Trạng thái thanh toán</div>
                    <div class="bp-detail__value">
                        @switch($order->payment_status)
                            @case('paid')
                                Đã thanh toán
                                @break
                            @case('unpaid')
                            @case('pending')
                                Chưa thanh toán
                                @break
                            @default
                                {{ $order->payment_status ?: 'Chưa xác định' }}
                        @endswitch
                    </div>
                </div>

                <div>
                    <div class="bp-detail__label">Số cửa hàng</div>
                    <div class="bp-detail__value">
                        {{ $order->vendorOrders->count() }}
                    </div>
                </div>
            </div>
        </section>
    </div>

    @forelse ($order->vendorOrders as $vendorOrder)
        @php
            $vendorStatus = $vendorOrder->status ?? 'pending';
        @endphp

        <section class="bp-detail__panel">
            <div class="bp-detail__vendor-head">
                <div>
                    <div class="bp-detail__vendor-name">
                        {{ $vendorOrder->vendor?->store_name ?? 'Cửa hàng' }}
                    </div>

                    <div class="bp-detail__vendor-code">
                        Đơn của cửa hàng: {{ $vendorOrder->vo_code }}
                    </div>
                </div>

                <span class="bp-detail__badge {{ $statusClasses[$vendorStatus] ?? '' }}">
                    {{ $statusLabels[$vendorStatus] ?? 'Chưa xác định' }}
                </span>
            </div>

            <div class="bp-detail__items">
                @forelse ($vendorOrder->items as $item)
                    <div class="bp-detail__item">
                        <div>
                            <div class="bp-detail__item-name">
                                {{ $item->product_name }}
                            </div>

                            <div class="bp-detail__item-meta">
                                SKU: {{ $item->sku_code }}
                                <div>
                                    Số lượng: {{ $item->qty }}
                                    × {{ $money($item->unit_price) }}
                                </div>
                            </div>
                        </div>

                        <div class="bp-detail__price">
                            {{ $money($item->line_total) }}
                        </div>
                    </div>
                @empty
                    <p class="bp-detail__subtitle">
                        Chưa có thông tin sản phẩm.
                    </p>
                @endforelse
            </div>

            <div class="bp-detail__vendor-total">
                <span>Tổng đơn của cửa hàng</span>
                <span class="bp-detail__price">
                    {{ $money($vendorOrder->total) }}
                </span>
            </div>
        </section>

        <section class="bp-detail__panel">
            <h2>
                Lịch sử trạng thái —
                {{ $vendorOrder->vendor?->store_name ?? 'Cửa hàng' }}
            </h2>

            <ol class="bp-detail__timeline">
                @if ($order->placed_at)
                    <li class="bp-detail__event">
                        <div class="bp-detail__event-title">
                            Đơn hàng được tạo
                        </div>

                        <div class="bp-detail__event-time">
                            {{ $order->placed_at->format('d/m/Y H:i') }}
                        </div>
                    </li>
                @endif

                @forelse ($vendorOrder->statusHistories as $history)
                    <li class="bp-detail__event">
                        <div class="bp-detail__event-title">
                            {{ $statusLabels[$history->to_status]
                                ?? $history->to_status
                                ?? 'Cập nhật trạng thái' }}
                        </div>

                        @if ($history->created_at)
                            <div class="bp-detail__event-time">
                                {{ $history->created_at->format('d/m/Y H:i') }}
                            </div>
                        @endif

                        @if ($history->note)
                            <div class="bp-detail__event-note">
                                {{ $history->note }}
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="bp-detail__event">
                        <div class="bp-detail__event-note">
                            Chưa có lịch sử cập nhật trạng thái.
                        </div>
                    </li>
                @endforelse
            </ol>

            @if ($vendorOrder->tracking_code)
                <div class="bp-detail__notice" style="margin-top: 20px;">
                    Mã vận đơn:
                    <strong>{{ $vendorOrder->tracking_code }}</strong>
                </div>
            @endif
        </section>
    @empty
        <section class="bp-detail__panel">
            <p class="bp-detail__subtitle">
                Đơn hàng chưa có thông tin cửa hàng.
            </p>
        </section>
    @endforelse

    <section class="bp-detail__panel">
        <h2>Tổng kết đơn hàng</h2>

        <div class="bp-detail__summary">
            <div class="bp-detail__summary-row">
                <span>Tạm tính</span>
                <strong>{{ $money($order->subtotal) }}</strong>
            </div>

            <div class="bp-detail__summary-row">
                <span>Phí vận chuyển</span>
                <strong>{{ $money($order->shipping_total) }}</strong>
            </div>

            <div class="bp-detail__summary-row">
                <span>Giảm giá</span>
                <strong>-{{ $money($order->discount_total) }}</strong>
            </div>

            <div class="bp-detail__summary-row bp-detail__summary-row--final">
                <span>Tổng thanh toán</span>
                <strong>{{ $money($order->grand_total) }}</strong>
            </div>
        </div>
    </section>

    @if ($canCancel)
        <div class="bp-detail__notice">
            Đơn hàng hiện đáp ứng điều kiện trạng thái để yêu cầu hủy.
            Chức năng hủy sẽ được kích hoạt sau khi tích hợp xử lý hủy
            đơn và hoàn tồn kho an toàn.
        </div>
    @endif

</div>
@endsection
