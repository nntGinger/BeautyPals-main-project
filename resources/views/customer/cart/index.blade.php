@extends('layouts.app')

@section('title', 'Giỏ hàng của tôi — BeautyPals')

@section('content')
    <div class="card">
        <h1>Giỏ hàng của tôi</h1>
        <p class="muted">Sản phẩm được tự động nhóm theo từng gian hàng (Vendor) để tính phí và tách đơn minh bạch.</p>
    </div>

    @if (empty($cartData['vendors']))
        <div class="card" style="text-align: center; padding: 2.5rem 1.5rem;">
            <h2>Giỏ hàng đang trống</h2>
            <p class="muted">Bạn chưa thêm sản phẩm nào vào giỏ hàng.</p>
            <a href="{{ route('catalog.index') }}" class="btn" style="margin-top: 0.75rem;">Tiếp tục mua sắm</a>
        </div>
    @else
        @foreach ($cartData['vendors'] as $group)
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <h2 style="margin: 0;">Gian hàng: {{ $group['vendor_name'] }}</h2>
                    <span class="badge">Tạm tính nhóm: {{ number_format($group['subtotal'], 0, ',', '.') }} ₫</span>
                </div>

                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Sản phẩm</th>
                                <th>Phân loại (SKU)</th>
                                <th>Đơn giá</th>
                                <th>Số lượng</th>
                                <th>Thành tiền</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group['items'] as $item)
                                <tr>
                                    <td>
                                        <a href="{{ route('catalog.show', $item['product_id']) }}">
                                            <strong>{{ $item['product_name'] }}</strong>
                                        </a>
                                    </td>
                                    <td><code>{{ $item['sku_code'] }}</code> · {{ $item['variant_name'] }}</td>
                                    <td>{{ number_format($item['unit_price'], 0, ',', '.') }} ₫</td>
                                    <td>
                                        <form method="POST" action="{{ route('customer.cart.update', $item['sku_id']) }}" style="display: flex; gap: 0.4rem; align-items: center;">
                                            @csrf
                                            @method('PUT')
                                            <label for="cart-qty-{{ $item['sku_id'] }}" hidden>Số lượng</label>
                                            <input id="cart-qty-{{ $item['sku_id'] }}" type="number" name="qty" value="{{ $item['qty'] }}" min="1" style="width: 74px; padding: 0.35rem;">
                                            <button type="submit" class="btn btn-ghost btn-sm">Cập nhật</button>
                                        </form>
                                    </td>
                                    <td><strong>{{ number_format($item['line_total'], 0, ',', '.') }} ₫</strong></td>
                                    <td>
                                        <form method="POST" action="{{ route('customer.cart.destroy', $item['sku_id']) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--danger);">Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        <div class="card" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <p class="muted" style="margin: 0;">Tổng số lượng: <strong>{{ $cartData['total_quantity'] }}</strong> sản phẩm ({{ count($cartData['vendors']) }} gian hàng)</p>
                <p style="font-size: 1.35rem; font-weight: 700; color: var(--accent-dark); margin: 0.25rem 0 0;">
                    Tổng thanh toán: {{ number_format($cartData['grand_total'], 0, ',', '.') }} ₫
                </p>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <a href="{{ route('catalog.index') }}" class="btn btn-ghost">Mua thêm</a>
                <a href="{{ route('customer.checkout.index') }}" class="btn">Tiến hành đặt hàng COD &rarr;</a>
            </div>
        </div>
    @endif
@endsection

