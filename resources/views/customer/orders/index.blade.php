
@extends('layouts.app')

@section('title', 'Đơn hàng của tôi - BeautyPals')

@push('styles')
<style>
    .bp-orders {
        display: grid;
        gap: 24px;
    }

    .bp-orders__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
    }

    .bp-orders__eyebrow {
        margin: 0 0 8px;
        color: var(--accent-dark);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
    }

    .bp-orders__title {
        margin: 0 0 8px;
        font-size: 32px;
        color: var(--ink);
    }

    .bp-orders__subtitle {
        margin: 0;
        color: var(--muted);
        line-height: 1.7;
    }

    .bp-orders__nav {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .bp-orders__link {
        display: inline-block;
        padding: 11px 16px;
        border: 1px solid var(--line);
        border-radius: 12px;
        background: #fff;
        color: var(--ink);
        font-weight: 600;
        text-decoration: none;
    }

    .bp-orders__link:hover {
        border-color: var(--accent);
    }

    .bp-orders__list {
        display: grid;
        gap: 18px;
    }

    .bp-orders__card {
        padding: 0;
        overflow: hidden;
    }

    .bp-orders__card-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding: 20px 24px;
        border-bottom: 1px solid var(--line);
        background: #fffaf8;
    }

    .bp-orders__code {
        display: block;
        font-size: 16px;
        font-weight: 700;
        color: var(--ink);
        overflow-wrap: anywhere;
    }

    .bp-orders__date {
        display: block;
        margin-top: 6px;
        color: var(--muted);
        font-size: 13px;
    }

    .bp-orders__badge {
        display: inline-block;
        padding: 7px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        background: #f1e8e4;
        color: #795e53;
    }

    .bp-orders__badge--pending {
        background: #fff0d8;
        color: #8d5a18;
    }

    .bp-orders__badge--done {
        background: #e5f5ec;
        color: #287247;
    }

    .bp-orders__badge--cancelled {
        background: #fce6e8;
        color: #a23848;
    }

    .bp-orders__card-body {
        padding: 24px;
        display: grid;
        gap: 18px;
    }

    .bp-orders__label {
        margin: 0 0 10px;
        color: var(--muted);
        font-size: 13px;
    }

    .bp-orders__vendors {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .bp-orders__vendor {
        display: inline-block;
        padding: 8px 11px;
        background: #faf6f3;
        border: 1px solid var(--line);
        border-radius: 10px;
        font-size: 12px;
        line-height: 1.5;
    }

    .bp-orders__card-bottom {
        border-top: 1px solid var(--line);
        padding-top: 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }

    .bp-orders__total-label {
        display: block;
        font-size: 13px;
        color: var(--muted);
        margin-bottom: 5px;
    }

    .bp-orders__total {
        font-size: 23px;
        font-weight: 700;
        color: var(--accent-dark);
    }

    .bp-orders__detail {
        display: inline-block;
        padding: 12px 18px;
        border-radius: 12px;
        background: var(--accent);
        color: #fff;
        text-decoration: none;
        font-size: 14px;
        font-weight: 700;
    }

    .bp-orders__detail:hover {
        background: var(--accent-dark);
        color: #fff;
        text-decoration: none;
    }

    .bp-orders__empty {
        padding: 48px 24px;
        text-align: center;
    }

    .bp-orders__empty-icon {
        display: block;
        font-size: 44px;
        margin-bottom: 16px;
    }

    .bp-orders__empty h2 {
        margin: 0 0 12px;
        font-size: 24px;
    }

    .bp-orders__empty p {
        margin: 0 auto 24px;
        max-width: 480px;
        color: var(--muted);
        line-height: 1.7;
    }

    .bp-orders__pagination {
        margin-top: 8px;
        overflow-x: auto;
    }

    .bp-orders a:focus-visible {
        outline: 3px solid var(--accent-dark);
        outline-offset: 3px;
    }

    @@media (max-width: 650px) {
        .bp-orders__title {
            font-size: 26px;
        }

        .bp-orders__card-top,
        .bp-orders__card-body {
            padding: 18px;
        }

        .bp-orders__card-bottom {
            align-items: stretch;
        }

        .bp-orders__detail {
            text-align: center;
            width: 100%;
        }

        .bp-orders__total {
            font-size: 21px;
        }
    }
</style>
@endpush

@section('content')

<div class="bp-orders">

    <header class="bp-orders__header">
        <div>
            <p class="bp-orders__eyebrow">
                BEAUTYPALS ORDERS
            </p>

            <h1 class="bp-orders__title">
                Đơn hàng của tôi
            </h1>

            <p class="bp-orders__subtitle">
                Theo dõi trạng thái và xem lại
                các đơn hàng bạn đã đặt.
            </p>
        </div>

        <nav class="bp-orders__nav" aria-label="Điều hướng mua hàng">
            <a
                href="{{ route('customer.cart.index') }}"
                class="bp-orders__link"
            >
                Giỏ hàng
            </a>
        </nav>
    </header>
    @forelse ($orders as $order)

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
                'rejected' => 'Từ chối',
            ];

            $status = $order->status ?? 'unknown';

            $statusLabel = $statusLabels[$status]
                ?? 'Không xác định';

            $statusClass = match ($status) {
                'pending' => 'bp-orders__badge--pending',
                'completed', 'delivered' => 'bp-orders__badge--done',
                'cancelled', 'rejected' => 'bp-orders__badge--cancelled',
                default => '',
            };
        @endphp

        <article class="card bp-orders__card">

            <div class="bp-orders__card-top">

                <div>
                    <span class="bp-orders__code">
                        Đơn hàng #{{ $order->order_code }}
                    </span>

                    <span class="bp-orders__date">
                        @if ($order->placed_at)
                            Đặt ngày
                            <time datetime="{{ $order->placed_at->toIso8601String() }}">
                                {{ $order->placed_at->format('d/m/Y H:i') }}
                            </time>
                        @else
                            Chưa có ngày đặt hàng
                        @endif
                    </span>
                </div>

                <span class="bp-orders__badge {{ $statusClass }}">
                    {{ $statusLabel }}
                </span>

            </div>

            <div class="bp-orders__card-body">

                <section aria-label="Các gian hàng trong đơn">

                    <p class="bp-orders__label">
                        {{ $order->vendorOrders->count() }} gian hàng
                    </p>

                    <div class="bp-orders__vendors">
                        @forelse ($order->vendorOrders as $vendorOrder)

                            <span class="bp-orders__vendor">
                                🏬
                                {{ $vendorOrder->vendor?->store_name ?? 'Gian hàng' }}
                            </span>

                        @empty

                            <span class="bp-orders__label">
                                Chưa có thông tin gian hàng.
                            </span>

                        @endforelse
                    </div>

                </section>

                <div class="bp-orders__card-bottom">

                    <div>
                        <span class="bp-orders__total-label">
                            Tổng thanh toán
                        </span>

                        <strong class="bp-orders__total">
                            {{ number_format($order->grand_total, 0, ',', '.') }} ₫
                        </strong>
                    </div>

                    <a
                        href="{{ route('customer.orders.show', $order) }}"
                        class="bp-orders__detail"
                        aria-label="Xem chi tiết đơn hàng {{ $order->order_code }}"
                    >
                        Xem chi tiết đơn hàng
                    </a>

                </div>
            </div>

        </article>

    @empty

        <section class="card bp-orders__empty">

            <span class="bp-orders__empty-icon" aria-hidden="true">
                📦
            </span>

            <h2>Bạn chưa có đơn hàng nào</h2>

            <p>
                Khi bạn đặt hàng tại BeautyPals,
                lịch sử mua sắm và trạng thái đơn hàng
                sẽ được hiển thị tại đây.
            </p>

            <a
                href="{{ route('customer.cart.index') }}"
                class="btn"
            >
                Xem giỏ hàng
            </a>

        </section>

    @endforelse

    @if ($orders->hasPages())
        <nav
            class="bp-orders__pagination"
            aria-label="Phân trang đơn hàng"
        >
            {{ $orders->links() }}
        </nav>
    @endif

</div>

@endsection
