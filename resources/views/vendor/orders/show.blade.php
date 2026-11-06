@extends('layouts.app')

@section('title', 'Xử lý kiện hàng ' . $vendorOrder->vo_code)

@section('content')
    <div style="margin-bottom: 1rem;">
        <a href="{{ route('vendor.orders.index') }}">&larr; Quay lại danh sách đơn hàng</a>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1>Kiện hàng {{ $vendorOrder->vo_code }}</h1>
                <p class="muted" style="margin: 0.25rem 0;">
                    Đơn tổng: <code>{{ $vendorOrder->order?->order_code }}</code> · Người nhận: <strong>{{ $vendorOrder->order?->receiver }}</strong> ({{ $vendorOrder->order?->phone }})<br>
                    Địa chỉ giao: {{ $vendorOrder->order?->address_text }}
                </p>
            </div>
            <div>
                <span class="badge" style="font-size: 0.95rem;">Trạng thái: {{ strtoupper($vendorOrder->status) }}</span>
            </div>
        </div>

        @error('to_status') <p class="error">{{ $message }}</p> @enderror
        @error('cancel_reason') <p class="error">{{ $message }}</p> @enderror

        @if (! empty($allowedNext))
            <hr style="border: 0; border-top: 1px solid var(--line); margin: 1.25rem 0;">
            <h2>Cập nhật trạng thái xử lý đơn</h2>
            <form method="POST" action="{{ route('vendor.orders.transition', $vendorOrder) }}">
                @csrf
                <div class="form-grid">
                    <div>
                        <label for="to_status">Chuyển sang trạng thái</label>
                        <select id="to_status" name="to_status" required>
                            @foreach ($allowedNext as $next)
                                <option value="{{ $next }}">{{ strtoupper($next) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="tracking_code">Mã vận đơn (khi gửi hàng / shipped)</label>
                        <input id="tracking_code" type="text" name="tracking_code" value="{{ old('tracking_code', $vendorOrder->tracking_code) }}" placeholder="VD: GHN-882910">
                    </div>
                    <div>
                        <label for="cancel_reason">Lý do từ chối (bắt buộc nếu chọn REJECTED)</label>
                        <input id="cancel_reason" type="text" name="cancel_reason" value="{{ old('cancel_reason') }}" placeholder="Nhập lý do nếu từ chối...">
                    </div>
                    <div>
                        <label for="note">Ghi chú nội bộ</label>
                        <input id="note" type="text" name="note" value="{{ old('note') }}" placeholder="Ghi chú thêm...">
                    </div>
                </div>
                <div style="margin-top: 1rem;">
                    <button type="submit">Xác nhận chuyển trạng thái</button>
                </div>
            </form>
        @endif
    </div>

    <div class="card">
        <h2>Chi tiết sản phẩm & Phân bổ lô FEFO</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>Mã SKU</th>
                        <th>Đơn giá</th>
                        <th>SL</th>
                        <th>Thành tiền</th>
                        <th>Tỷ lệ HH</th>
                        <th>Tiền HH</th>
                        <th>Lô xuất FEFO</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vendorOrder->items as $item)
                        <tr>
                            <td><strong>{{ $item->product_name }}</strong></td>
                            <td><code>{{ $item->sku_code }}</code></td>
                            <td>{{ number_format($item->unit_price, 0, ',', '.') }} ₫</td>
                            <td>{{ $item->qty }}</td>
                            <td><strong>{{ number_format($item->line_total, 0, ',', '.') }} ₫</strong></td>
                            <td>{{ $item->commission_rate }}%</td>
                            <td>{{ number_format($item->commission_amount, 0, ',', '.') }} ₫</td>
                            <td>
                                @foreach ($item->allocations as $alloc)
                                    <div>
                                        <span class="badge">{{ $alloc->batch?->batch_no }} (SL: {{ $alloc->qty }} · {{ $alloc->status }})</span>
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Lịch sử chuyển trạng thái</h2>
        <ul class="muted" style="margin: 0; padding-left: 1.25rem;">
            @foreach ($vendorOrder->statusHistories as $hist)
                <li>
                    [{{ $hist->created_at }}]
                    <code>{{ $hist->from_status ?? 'khởi tạo' }}</code> &rarr; <strong><code>{{ $hist->to_status }}</code></strong>
                    @if ($hist->changer) (bởi {{ $hist->changer->name }}) @endif
                    @if ($hist->note) — {{ $hist->note }} @endif
                </li>
            @endforeach
        </ul>
    </div>
@endsection

