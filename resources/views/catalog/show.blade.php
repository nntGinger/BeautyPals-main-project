@extends('layouts.app')

@section('title', $product->name . ' — BeautyPals')

@section('content')
    <div style="margin-bottom: 1rem;">
        <a href="{{ route('catalog.index') }}">&larr; Quay lại danh sách sản phẩm</a>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; align-items: flex-start;">
            <div style="flex: 1; min-width: 280px;">
                <p class="muted" style="margin: 0 0 0.35rem;">
                    Thương hiệu: <strong>{{ $product->brand?->name ?? 'Chính hãng' }}</strong>
                    · Danh mục: <strong>{{ $product->category?->name }}</strong>
                </p>
                <h1>{{ $product->name }}</h1>
                <p class="muted">
                    Gian hàng phân phối: <strong>{{ $product->vendor?->store_name }}</strong>
                </p>

                @if ($totalAvailable > 0)
                    <p>
                        <span class="badge badge-ok">Còn hàng ({{ $totalAvailable }} đơn vị khả dụng)</span>
                        @if ($nearestExpDate)
                            <span class="badge" style="margin-left: 0.4rem;">
                                Hạn lô xuất sớm nhất (FEFO): {{ $nearestExpDate->format('d/m/Y') }}
                            </span>
                        @endif
                    </p>
                @else
                    <p>
                        <span class="badge badge-danger">Tạm hết hàng</span>
                    </p>
                @endif

                @if ($product->description)
                    <div style="margin-top: 1rem; line-height: 1.6;">
                        <h2>Mô tả sản phẩm</h2>
                        <p>{{ $product->description }}</p>
                    </div>
                @endif
            </div>
        </div>

        <hr style="border: 0; border-top: 1px solid var(--line); margin: 1.5rem 0;">

        <h2>Phân loại (SKU) & Đặt mua</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mã SKU</th>
                        <th>Phân loại</th>
                        <th>Dung tích</th>
                        <th>Đơn giá</th>
                        <th>Tồn khả dụng</th>
                        <th>Hạn dùng gần nhất</th>
                        <th>Mua hàng</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($product->skus as $sku)
                        <tr>
                            <td><code>{{ $sku->sku_code }}</code></td>
                            <td><strong>{{ $sku->variant_name }}</strong></td>
                            <td>{{ $sku->size ?? '—' }}</td>
                            <td><strong>{{ number_format($sku->price, 0, ',', '.') }} ₫</strong></td>
                            <td>
                                @if ($sku->available_stock > 0)
                                    <span class="badge badge-ok">{{ $sku->available_stock }}</span>
                                @else
                                    <span class="badge badge-danger">Hết hàng</span>
                                @endif
                            </td>
                            <td>
                                {{ $sku->nearest_exp_date ? $sku->nearest_exp_date->format('d/m/Y') : '—' }}
                            </td>
                            <td>
                                @if ($sku->available_stock > 0)
                                    @auth
                                        @if (auth()->user()->isCustomer())
                                            <form method="POST" action="{{ route('customer.cart.store') }}" style="display: flex; gap: 0.4rem; align-items: center;">
                                                @csrf
                                                <input type="hidden" name="sku_id" value="{{ $sku->id }}">
                                                <label for="qty-{{ $sku->id }}" class="sr-only" hidden>Số lượng</label>
                                                <input id="qty-{{ $sku->id }}" type="number" name="qty" value="1" min="1" max="{{ $sku->available_stock }}" style="width: 74px; padding: 0.4rem;">
                                                <button type="submit" class="btn btn-sm">Thêm vào giỏ</button>
                                            </form>
                                        @else
                                            <span class="muted">Chỉ tài khoản khách mua mới đặt hàng</span>
                                        @endif
                                    @else
                                        <a href="{{ route('login') }}" class="btn btn-sm">Đăng nhập để mua</a>
                                    @endauth
                                @else
                                    <span class="muted">Không thể đặt</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @error('sku_id') <p class="error">{{ $message }}</p> @enderror
        @error('qty') <p class="error">{{ $message }}</p> @enderror
    </div>

    <div class="card">
        <h2>Bảng thành phần chuẩn hóa (INCI)</h2>
        @if ($product->ingredients->isEmpty())
            <p class="muted">Sản phẩm chưa cập nhật danh sách thành phần chi tiết.</p>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Thứ tự</th>
                            <th>Tên chuẩn INCI</th>
                            <th>Tên thường gọi</th>
                            <th>Tên gọi khác (Aliases)</th>
                            <th>Nồng độ (%)</th>
                            <th>Công dụng</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($product->ingredients as $ing)
                            <tr>
                                <td>#{{ $ing->pivot->position }}</td>
                                <td><strong>{{ $ing->inci_name }}</strong></td>
                                <td>{{ $ing->display_name }}</td>
                                <td>{{ $ing->aliases->pluck('alias')->join(', ') ?: '—' }}</td>
                                <td>{{ $ing->pivot->concentration !== null ? $ing->pivot->concentration . '%' : '—' }}</td>
                                <td>{{ $ing->function ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection

