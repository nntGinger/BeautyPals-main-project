@extends('layouts.app')

@section('title', 'Thanh toán COD - BeautyPals')

@push('styles')
<style>
    .bp-checkout {
        display: grid;
        gap: 22px;
    }

    .bp-checkout__eyebrow {
        margin: 0 0 8px;
        color: var(--accent-dark);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
    }

    .bp-checkout__header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }

    .bp-checkout__header h1 {
        margin: 0 0 8px;
        font-size: 30px;
    }

    .bp-checkout__subtitle {
        color: var(--muted);
        margin: 0;
        line-height: 1.6;
    }

    .bp-checkout__back {
        padding: 11px 16px;
        border: 1px solid var(--line);
        border-radius: 12px;
        background: white;
        color: var(--ink);
        font-weight: 600;
        font-size: 14px;
    }

    .bp-checkout__back:hover {
        text-decoration: none;
        border-color: var(--accent);
    }

    .bp-checkout__content {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 290px;
        gap: 18px;
        align-items: start;
    }

    .bp-checkout__left {
        display: grid;
        gap: 18px;
        min-width: 0;
    }

    .bp-checkout__card {
        padding: 24px;
    }

    .bp-checkout__card h2 {
        margin: 0 0 10px;
        font-size: 19px;
    }

    .bp-checkout__description {
        color: var(--muted);
        font-size: 14px;
        line-height: 1.6;
        margin: 0 0 18px;
    }

    .bp-checkout__addresses {
        display: grid;
        gap: 12px;
        border: 0;
        padding: 0;
        margin: 0;
        min-width: 0;
    }

    .bp-checkout__address {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px;
        border: 1px solid var(--line);
        border-radius: 12px;
        cursor: pointer;
        margin: 0;
        background: #fff;
        font-weight: 400;
        transition: border-color .15s, background .15s;
    }

    .bp-checkout__address:hover,
    .bp-checkout__address:has(input:checked) {
        border-color: var(--accent);
        background: #fff8f8;
    }

    .bp-checkout__address input[type="radio"] {
        flex: 0 0 auto;
        width: 18px;
        height: 18px;
        margin: 3px 0 0;
        accent-color: var(--accent);
    }

    .bp-checkout__address-info {
        min-width: 0;
        display: grid;
        gap: 6px;
        font-size: 14px;
        line-height: 1.5;
    }

    .bp-checkout__address-name {
        font-size: 15px;
        font-weight: 700;
    }

    .bp-checkout__address-detail {
        color: var(--muted);
        overflow-wrap: anywhere;
    }

    .bp-checkout__tag {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 999px;
        background: #fbe7eb;
        color: var(--accent-dark);
        font-size: 11px;
        font-weight: 700;
    }

    .bp-checkout__vendor {
        padding: 16px 0;
        border-bottom: 1px solid var(--line);
    }

    .bp-checkout__vendor:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }

    .bp-checkout__vendor-title {
        margin: 0 0 12px;
        font-size: 15px;
        color: var(--accent-dark);
    }

    .bp-checkout__item {
        padding: 8px 0;
        display: flex;
        justify-content: space-between;
        gap: 12px;
        font-size: 13px;
    }

    .bp-checkout__item-name {
        margin: 0 0 5px;
        font-weight: 600;
        line-height: 1.5;
    }

    .bp-checkout__item-meta {
        color: var(--muted);
        margin: 0;
        line-height: 1.5;
    }

    .bp-checkout__item-price {
        font-weight: 700;
        text-align: right;
        white-space: nowrap;
    }

    .bp-checkout__vendor-total {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        margin-top: 12px;
        font-size: 13px;
    }

    .bp-checkout__summary {
        position: sticky;
        top: 18px;
        padding: 24px;
    }

    .bp-checkout__summary-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 0;
        font-size: 14px;
    }

    .bp-checkout__summary-row span {
        color: var(--muted);
    }

    .bp-checkout__total {
        border-top: 1px solid var(--line);
        margin-top: 12px;
        padding-top: 18px;
    }

    .bp-checkout__total strong {
        color: var(--accent-dark);
        font-size: 23px;
        white-space: nowrap;
    }

    .bp-checkout__payment {
        background: #faf6f3;
        border-radius: 12px;
        padding: 14px;
        margin: 16px 0;
    }

    .bp-checkout__payment strong {
        display: block;
        margin-bottom: 6px;
        font-size: 14px;
    }

    .bp-checkout__payment p {
        color: var(--muted);
        font-size: 12px;
        line-height: 1.6;
        margin: 0;
    }

    .bp-checkout__submit {
        display: block;
        width: 100%;
        padding: 14px 16px;
        border-radius: 12px;
        font-size: 14px;
    }

    .bp-checkout__submit:disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    .bp-checkout__notice {
        margin-top: 12px;
        padding: 12px;
        border: 1px solid #efddbd;
        background: #fff8ee;
        border-radius: 10px;
        color: #795a27;
        font-size: 13px;
        line-height: 1.6;
    }

    .bp-checkout__empty {
        padding: 32px 24px;
        text-align: center;
    }

    .bp-checkout__empty p {
        color: var(--muted);
        line-height: 1.6;
    }

    .bp-checkout a:focus-visible,
    .bp-checkout button:focus-visible,
    .bp-checkout input:focus-visible {
        outline: 3px solid var(--accent-dark);
        outline-offset: 3px;
    }

    @@media (max-width: 760px) {
        .bp-checkout__content {
            grid-template-columns: 1fr;
        }

        .bp-checkout__summary {
            position: static;
        }

        .bp-checkout__header h1 {
            font-size: 25px;
        }
    }
</style>
@endpush

@section('content')
@php
    $checkoutReady =
        \Illuminate\Support\Facades\Route::has('customer.checkout.store')
        && \Illuminate\Support\Facades\Route::has('customer.orders.show')
        && class_exists(\App\Services\CheckoutService::class);
@endphp

<div class="bp-checkout">

    <header class="bp-checkout__header">
        <div>
            <p class="bp-checkout__eyebrow">
                BeautyPals Checkout
            </p>

            <h1>Xác nhận đặt hàng</h1>

            <p class="bp-checkout__subtitle">
                Kiểm tra sản phẩm, chọn địa chỉ nhận hàng
                và thanh toán khi nhận hàng (COD).
            </p>
        </div>

        <a
            href="{{ route('customer.cart.index') }}"
            class="bp-checkout__back"
        >
            ← Quay lại giỏ hàng
        </a>
    </header>

    @error('cart')
        <div class="flash-error" role="alert">
            {{ $message }}
        </div>
    @enderror

    @error('idempotency_key')
        <div class="flash-error" role="alert">
            {{ $message }}
        </div>
    @enderror

    @if ($addresses->isEmpty())

        <section class="card bp-checkout__empty">
            <h2>Chưa có địa chỉ nhận hàng</h2>

            <p>
                Bạn cần có ít nhất một địa chỉ giao hàng
                trước khi thực hiện thanh toán COD.
            </p>

            @if (\Illuminate\Support\Facades\Route::has('customer.addresses.index'))
                <a
                    href="{{ route('customer.addresses.index') }}"
                    class="btn"
                >
                    Quản lý địa chỉ
                </a>
            @else
                <p class="bp-checkout__notice" role="status">
                    Chức năng quản lý địa chỉ đang chờ tích hợp.
                </p>
            @endif
        </section>

    @else

        <form
            id="bp-checkout-form"
            method="POST"
            action="{{ \Illuminate\Support\Facades\Route::has('customer.checkout.store') ? route('customer.checkout.store') : '#' }}"
        >
            @csrf

            <input
                type="hidden"
                name="idempotency_key"
                value="{{ old('idempotency_key', $idempotencyKey) }}"
            >

            <div class="bp-checkout__content">

                <div class="bp-checkout__left">

                    <section class="card bp-checkout__card">
                        <h2>01. Địa chỉ nhận hàng</h2>

                        <p class="bp-checkout__description">
                            Chọn địa chỉ giao hàng phù hợp với bạn.
                        </p>

                        @error('address_id')
                            <p class="error" id="address-error" role="alert">
                                {{ $message }}
                            </p>
                        @enderror

                        <fieldset
                            class="bp-checkout__addresses"
                            aria-label="Chọn địa chỉ nhận hàng"
                        >

                            @foreach ($addresses as $address)

                                <label class="bp-checkout__address">

                                    <input
                                        type="radio"
                                        name="address_id"
                                        value="{{ $address->id }}"
                                        @checked(
                                            (string) old('address_id', $addresses->first()->id)
                                            === (string) $address->id
                                        )
                                        required
                                    >

                                    <span class="bp-checkout__address-info">

                                        <span>
                                            <span class="bp-checkout__address-name">
                                                {{ $address->receiver }}
                                            </span>

                                            @if ($address->is_default)
                                                <span class="bp-checkout__tag">
                                                    Mặc định
                                                </span>
                                            @endif
                                        </span>

                                        <span>
                                            {{ $address->phone }}
                                        </span>

                                        <span class="bp-checkout__address-detail">
                                            {{ $address->line }},
                                            {{ $address->ward }},
                                            {{ $address->district }},
                                            {{ $address->city }}
                                        </span>

                                    </span>
                                </label>

                            @endforeach

                        </fieldset>
                    </section>

                    <section class="card bp-checkout__card">
                        <h2>02. Sản phẩm trong đơn hàng</h2>

                        <p class="bp-checkout__description">
                            Sản phẩm được phân nhóm theo từng gian hàng.
                        </p>

                        @foreach ($cartData['vendors'] as $group)

                            <div class="bp-checkout__vendor">

                                <h3 class="bp-checkout__vendor-title">
                                    🏬 {{ $group['vendor_name'] }}
                                </h3>

                                @foreach ($group['items'] as $item)

                                    <div class="bp-checkout__item">

                                        <div>
                                            <p class="bp-checkout__item-name">
                                                {{ $item['product_name'] }}
                                            </p>

                                            <p class="bp-checkout__item-meta">
                                                {{ $item['variant_name'] ?: 'Mặc định' }}
                                            </p>

                                            <p class="bp-checkout__item-meta">
                                                SKU: {{ $item['sku_code'] }}
                                                · SL: {{ $item['qty'] }}
                                            </p>
                                        </div>

                                        <div class="bp-checkout__item-price">
                                            {{ number_format($item['line_total'], 0, ',', '.') }} ₫
                                        </div>

                                    </div>

                                @endforeach

                                <div class="bp-checkout__vendor-total">
                                    <span>Tạm tính gian hàng</span>

                                    <strong>
                                        {{ number_format($group['subtotal'], 0, ',', '.') }} ₫
                                    </strong>
                                </div>

                            </div>

                        @endforeach

                    </section>

                </div>

                <aside class="card bp-checkout__summary">
                    <h2>Tóm tắt đơn hàng</h2>

                    <div class="bp-checkout__summary-row">
                        <span>Sản phẩm</span>
                        <strong>{{ $cartData['total_quantity'] }}</strong>
                    </div>

                    <div class="bp-checkout__summary-row">
                        <span>Gian hàng</span>
                        <strong>{{ count($cartData['vendors']) }}</strong>
                    </div>

                    <div class="bp-checkout__summary-row bp-checkout__total">
                        <span>Tạm tính</span>

                        <strong>
                            {{ number_format($cartData['grand_total'], 0, ',', '.') }} ₫
                        </strong>
                    </div>

                    <p class="bp-checkout__description">
                        Tổng tiền sản phẩm chưa bao gồm các điều chỉnh
                        phí vận chuyển hoặc ưu đãi khi xử lý đơn hàng.
                    </p>

                    <div class="bp-checkout__payment">
                        <strong>Thanh toán khi nhận hàng (COD)</strong>

                        <p>
                            Bạn sẽ thanh toán khi nhận được đơn hàng.
                        </p>
                    </div>

                    @if ($checkoutReady)

                        <button
                            type="submit"
                            id="bp-checkout-submit"
                            class="bp-checkout__submit"
                        >
                            Đặt hàng COD
                        </button>

                    @else

                        <button
                            type="button"
                            class="bp-checkout__submit"
                            disabled
                        >
                            Đặt hàng COD
                        </button>

                        <div class="bp-checkout__notice" role="status">
                            Chức năng đặt hàng đang chờ tích hợp
                            CheckoutService và trang chi tiết đơn hàng.
                        </div>

                    @endif
                </aside>

            </div>
        </form>

    @endif

</div>
@endsection

@push('scripts')
<script>
    (() => {
        const form = document.getElementById('bp-checkout-form');
        const button = document.getElementById('bp-checkout-submit');

        if (!form || !button) {
            return;
        }

        const originalLabel = button.textContent.trim();

        form.addEventListener('submit', (event) => {
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }

            if (!form.checkValidity()) {
                return;
            }

            form.dataset.submitting = 'true';
            button.disabled = true;
            button.textContent = 'Đang xử lý đơn hàng...';
        });

        window.addEventListener('pageshow', () => {
            form.dataset.submitting = 'false';
            button.disabled = false;
            button.textContent = originalLabel;
        });
    })();
</script>
@endpush
