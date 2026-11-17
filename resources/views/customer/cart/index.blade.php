
@extends('layouts.app')

@section('title', 'Giỏ hàng của tôi - BeautyPals')

@push('styles')
<style>
    .bp-cart {
        display: grid;
        gap: 22px;
    }

    .bp-cart__header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }

    .bp-cart__eyebrow {
        color: var(--accent-dark);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        margin: 0 0 8px;
    }

    .bp-cart__header h1 {
        font-size: 30px;
        margin: 0 0 8px;
    }

    .bp-cart__subtitle {
        color: var(--muted);
        margin: 0;
        line-height: 1.6;
    }

    .bp-cart__secondary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--line);
        border-radius: 12px;
        padding: 11px 16px;
        background: white;
        color: var(--ink);
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
    }

    .bp-cart__secondary:hover {
        border-color: var(--accent);
        text-decoration: none;
    }

    .bp-cart__empty {
        text-align: center;
        padding: 55px 20px;
    }

    .bp-cart__empty-icon {
        font-size: 44px;
        margin-bottom: 18px;
    }

    .bp-cart__empty h2 {
        margin: 0 0 10px;
        font-size: 23px;
    }

    .bp-cart__empty p {
        color: var(--muted);
        margin-bottom: 24px;
    }

    .bp-cart__stats {
        color: var(--muted);
        font-size: 14px;
    }

    .bp-cart__vendor {
        padding: 0;
        overflow: hidden;
    }

    .bp-cart__vendor-header {
        padding: 19px 22px;
        background: #fff8f7;
        border-bottom: 1px solid var(--line);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }

    .bp-cart__vendor-header h2 {
        margin: 0;
        font-size: 18px;
    }

    .bp-cart__tag {
        font-size: 12px;
        font-weight: 600;
        border-radius: 999px;
        background: #fbe4e7;
        color: var(--accent-dark);
        padding: 6px 12px;
    }

    .bp-cart__item {
        padding: 22px;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        gap: 18px;
        border-bottom: 1px solid var(--line);
    }

    .bp-cart__product {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        min-width: 0;
    }

    .bp-cart__placeholder {
        flex: 0 0 66px;
        height: 72px;
        border-radius: 12px;
        background: #faece9;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--accent-dark);
        font-size: 14px;
        font-weight: 800;
    }

    .bp-cart__product h3 {
        margin: 0 0 7px;
        font-size: 15px;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .bp-cart__meta {
        margin: 4px 0;
        color: var(--muted);
        font-size: 13px;
    }

    .bp-cart__price {
        margin: 10px 0 0;
        color: var(--accent-dark);
        font-weight: 700;
    }

    .bp-cart__actions {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 10px;
    }

    .bp-cart__quantity {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .bp-cart__quantity label {
        margin: 0;
        font-size: 13px;
    }

    .bp-cart__quantity input {
        width: 74px;
        padding: 8px;
        border-radius: 9px;
        text-align: center;
    }

    .bp-cart__small-button {
        border-radius: 9px;
        padding: 9px 12px;
        font-size: 13px;
    }

    .bp-cart__remove {
        background: transparent;
        color: var(--danger);
        padding: 4px 8px;
        font-size: 13px;
    }

    .bp-cart__remove:hover {
        background: #fff0ee;
        color: var(--danger);
    }

    .bp-cart__line-total {
        font-size: 14px;
        font-weight: 700;
        text-align: right;
    }

    .bp-cart__vendor-footer {
        padding: 16px 22px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        font-size: 14px;
    }

    .bp-cart__vendor-footer strong {
        color: var(--accent-dark);
        font-size: 17px;
    }

    .bp-cart__summary {
        padding: 24px;
    }

    .bp-cart__summary h2 {
        margin: 0 0 18px;
        font-size: 20px;
    }

    .bp-cart__summary-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 9px 0;
    }

    .bp-cart__summary-row span:first-child {
        color: var(--muted);
    }

    .bp-cart__grand-total {
        border-top: 1px solid var(--line);
        margin-top: 12px;
        padding-top: 20px;
        font-weight: 700;
    }

    .bp-cart__grand-total strong {
        color: var(--accent-dark);
        font-size: 24px;
    }

    .bp-cart__checkout {
        display: block;
        text-align: center;
        margin-top: 20px;
        padding: 14px;
        border-radius: 12px;
    }

    .bp-cart__notice {
        margin: 18px 0 0;
        padding: 12px 14px;
        background: #fff8ee;
        border: 1px solid #efddbd;
        border-radius: 10px;
        color: #795a27;
        font-size: 13px;
        line-height: 1.6;
    }

    .bp-cart button:focus-visible,
    .bp-cart a:focus-visible,
    .bp-cart input:focus-visible {
        outline: 3px solid var(--accent-dark);
        outline-offset: 3px;
    }

    @@media (max-width: 650px) {
        .bp-cart__header h1 {
            font-size: 25px;
        }

        .bp-cart__item {
            grid-template-columns: 1fr;
            padding: 18px;
        }

        .bp-cart__actions {
            align-items: flex-start;
        }

        .bp-cart__line-total {
            text-align: left;
        }

        .bp-cart__vendor-header {
            padding: 16px 18px;
        }
    }
</style>
@endpush

@section('content')
<div class="bp-cart">

    <header class="bp-cart__header">
        <div>
            <p class="bp-cart__eyebrow">BeautyPals Shopping</p>

            <h1>Giỏ hàng của tôi</h1>

            <p class="bp-cart__subtitle">
                Quản lý sản phẩm theo từng gian hàng
                và kiểm tra tổng tiền trước khi đặt hàng.
            </p>
        </div>

        <a href="{{ route('home') }}"
           class="bp-cart__secondary">
            ← Tiếp tục mua sắm
        </a>
    </header>

    @if (empty($cartData['vendors']))

        <section class="card bp-cart__empty" role="status">
            <div class="bp-cart__empty-icon" aria-hidden="true">
                🛍️
            </div>

            <h2>Giỏ hàng của bạn đang trống</h2>

            <p>
                Hãy khám phá những sản phẩm yêu thích
                và thêm chúng vào giỏ hàng nhé.
            </p>

            <a href="{{ route('home') }}" class="btn">
                Khám phá sản phẩm
            </a>
        </section>

    @else

        <div class="bp-cart__stats">
            {{ $cartData['total_quantity'] }} sản phẩm
            từ {{ count($cartData['vendors']) }} gian hàng
        </div>

        @foreach ($cartData['vendors'] as $group)

            <section class="card bp-cart__vendor"
                     aria-labelledby="vendor-{{ $group['vendor_id'] }}">

                <header class="bp-cart__vendor-header">
                    <h2 id="vendor-{{ $group['vendor_id'] }}">
                        🏬 {{ $group['vendor_name'] }}
                    </h2>

                    <span class="bp-cart__tag">
                        Gian hàng
                    </span>
                </header>

                @foreach ($group['items'] as $item)

                    <article class="bp-cart__item">

                        <div class="bp-cart__product">
                            <div class="bp-cart__placeholder"
                                 aria-hidden="true">
                                BP
                            </div>

                            <div>
                                <h3>
                                    {{ $item['product_name'] }}
                                </h3>

                                <p class="bp-cart__meta">
                                    Phân loại:
                                    {{ $item['variant_name'] ?: 'Mặc định' }}
                                </p>

                                <p class="bp-cart__meta">
                                    SKU: {{ $item['sku_code'] }}
                                </p>

                                <p class="bp-cart__price">
                                    {{ number_format($item['unit_price'], 0, ',', '.') }} ₫
                                </p>
                            </div>
                        </div>

                        <div class="bp-cart__actions">

                            <form method="POST"
                                  action="{{ route('customer.cart.update', $item['sku_id']) }}"
                                  class="bp-cart__quantity">

                                @csrf
                                @method('PUT')

                                <label for="qty-{{ $item['cart_item_id'] }}">
                                    Số lượng
                                </label>

                                <input
                                    type="number"
                                    id="qty-{{ $item['cart_item_id'] }}"
                                    name="qty"
                                    value="{{ $item['qty'] }}"
                                    min="1"
                                    max="4294967295"
                                    step="1"
                                    required
                                >

                                <button type="submit"
                                        class="bp-cart__small-button">
                                    Cập nhật
                                </button>
                            </form>

                            <div class="bp-cart__line-total">
                                Thành tiền:
                                {{ number_format($item['line_total'], 0, ',', '.') }} ₫
                            </div>

                            <form method="POST"
                                  action="{{ route('customer.cart.destroy', $item['sku_id']) }}">

                                @csrf
                                @method('DELETE')

                                <button type="submit"
                                        class="bp-cart__remove"
                                        aria-label="Xóa {{ $item['product_name'] }} khỏi giỏ hàng">
                                    Xóa sản phẩm
                                </button>
                            </form>

                        </div>
                    </article>

                @endforeach

                <footer class="bp-cart__vendor-footer">
                    <span>Tạm tính gian hàng</span>

                    <strong>
                        {{ number_format($group['subtotal'], 0, ',', '.') }} ₫
                    </strong>
                </footer>
            </section>

        @endforeach

        <section class="card bp-cart__summary">
            <h2>Tổng kết giỏ hàng</h2>

            <div class="bp-cart__summary-row">
                <span>Số lượng sản phẩm</span>
                <strong>
                    {{ $cartData['total_quantity'] }}
                </strong>
            </div>

            <div class="bp-cart__summary-row">
                <span>Số gian hàng</span>
                <strong>
                    {{ count($cartData['vendors']) }}
                </strong>
            </div>

            <div class="bp-cart__summary-row bp-cart__grand-total">
                <span>Tổng tiền sản phẩm</span>

                <strong>
                    {{ number_format($cartData['grand_total'], 0, ',', '.') }} ₫
                </strong>
            </div>

            @if (\Illuminate\Support\Facades\Route::has('customer.checkout.index'))
                <a href="{{ route('customer.checkout.index') }}"
                   class="btn bp-cart__checkout">
                    Tiến hành đặt hàng COD
                </a>
            @else
                <p class="bp-cart__notice" role="status">
                    Chức năng đặt hàng COD sẽ được kích hoạt
                    sau khi hoàn tất tích hợp CheckoutService.
                </p>
            @endif

        </section>

    @endif

</div>
@endsection
