@extends('layouts.app')

@section('title', 'Thanh toán đơn hàng COD — BeautyPals')

@section('content')
    <div class="card">
        <h1>Xác nhận đặt hàng (Thanh toán khi nhận hàng - COD)</h1>
        <p class="muted">Đơn hàng sẽ được tự động tách cho từng gian hàng và giữ chỗ tồn kho từ lô có hạn dùng gần nhất (FEFO).</p>
        @error('cart') <div class="flash-error">{{ $message }}</div> @enderror
        @error('idempotency_key') <div class="flash-error">{{ $message }}</div> @enderror
    </div>

    @if ($addresses->isEmpty())
        <div class="card">
            <h2>Bạn chưa có địa chỉ nhận hàng</h2>
            <p class="muted">Vui lòng thêm ít nhất một địa chỉ giao hàng trước khi hoàn tất đặt đơn.</p>
            <a href="{{ route('customer.addresses.index') }}" class="btn">Thêm địa chỉ giao hàng</a>
        </div>
    @else
        <form method="POST" action="{{ route('customer.checkout.store') }}" id="checkout-form" novalidate>
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">

            <div class="card">
                <h2>1. Chọn địa chỉ nhận hàng</h2>
                @error('address_id') <p class="error">{{ $message }}</p> @enderror

                <div class="stack" style="margin-top: 0.75rem;">
                    @foreach ($addresses as $addr)
                        <label style="display: flex; align-items: flex-start; gap: 0.65rem; padding: 0.85rem; border: 1px solid var(--line); border-radius: 12px; cursor: pointer; margin: 0;">
                            <input type="radio" name="address_id" value="{{ $addr->id }}" @checked(old('address_id', $addresses->first()->id) == $addr->id) required style="margin-top: 0.25rem;">
                            <span>
                                <strong>{{ $addr->receiver }}</strong> ({{ $addr->phone }})
                                @if ($addr->is_default)
                                    <span class="badge badge-ok" style="margin-left: 0.35rem;">Mặc định</span>
                                @endif
                                <br>
                                <span class="muted">{{ $addr->line }}, {{ $addr->ward }}, {{ $addr->district }}, {{ $addr->city }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <div style="margin-top: 0.75rem;">
                    <a href="{{ route('customer.addresses.index') }}">+ Quản lý hoặc thêm địa chỉ mới</a>
                </div>
            </div>

            <div class="card">
                <h2>2. Chi tiết kiện hàng theo Vendor</h2>
                @foreach ($cartData['vendors'] as $group)
                    <div style="margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid var(--line);">
                        <p style="margin: 0 0 0.5rem;">
                            Gian hàng: <strong>{{ $group['vendor_name'] }}</strong>
                            · Tạm tính: <strong>{{ number_format($group['subtotal'], 0, ',', '.') }} ₫</strong>
                        </p>
                        <ul style="margin: 0; padding-left: 1.25rem;">
                            @foreach ($group['items'] as $item)
                                <li>
                                    {{ $item['product_name'] }} ({{ $item['variant_name'] }} - <code>{{ $item['sku_code'] }}</code>)
                                    × {{ $item['qty'] }} = <strong>{{ number_format($item['line_total'], 0, ',', '.') }} ₫</strong>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach

                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <p class="muted" style="margin: 0;">Phương thức thanh toán: <strong>Thanh toán tiền mặt khi nhận hàng (COD)</strong></p>
                        <p style="font-size: 1.4rem; font-weight: 700; color: var(--accent-dark); margin: 0.3rem 0 0;">
                            Tổng cộng: {{ number_format($cartData['grand_total'], 0, ',', '.') }} ₫
                        </p>
                    </div>
                    <button type="submit" id="checkout-submit-btn">Đặt hàng ngay (COD)</button>
                </div>
            </div>
        </form>

        @push('scripts')
            <script>
                document.getElementById('checkout-form')?.addEventListener('submit', function () {
                    const btn = document.getElementById('checkout-submit-btn');
                    if (btn) {
                        btn.disabled = true;
                        btn.textContent = 'Đang xử lý đơn hàng...';
                    }
                });
            </script>
        @endpush
    @endif
@endsection

