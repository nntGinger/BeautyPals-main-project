@extends('layouts.app')

@section('title', 'Chi tiết đơn hàng ' . $vendorOrder->vo_code)

@section('content')
    <div style="margin-bottom: 1rem;">
        <a href="{{ route('admin.orders.index') }}">&larr; Quay lại danh sách đơn hàng toàn sàn</a>
    </div>

    <div class="card">
        <h1>Kiện hàng {{ $vendorOrder->vo_code }} (Đơn cha: {{ $vendorOrder->order?->order_code }})</h1>
        <p class="muted">
            Gian hàng: <strong>{{ $vendorOrder->vendor?->store_name }}</strong>
            · Trạng thái kiện hàng: <span class="badge">{{ strtoupper($vendorOrder->status) }}</span>
            · Trạng thái đơn cha: <span class="badge">{{ strtoupper($vendorOrder->order?->status) }}</span>
        </p>
        <p>
            Người nhận: <strong>{{ $vendorOrder->order?->receiver }}</strong> ({{ $vendorOrder->order?->phone }}) — {{ $vendorOrder->order?->address_text }}
        </p>
    </div>

    <div class="card">
        <h2>Chi tiết dòng hàng & Hoa hồng đã chụp</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>SKU</th>
                        <th>Đơn giá</th>
                        <th>SL</th>
                        <th>Thành tiền</th>
                        <th>Tỷ lệ HH</th>
                        <th>Tiền HH</th>
                        <th>Lô FEFO</th>
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
                                    <span class="badge">{{ $alloc->batch?->batch_no }} ({{ $alloc->qty }} · {{ $alloc->status }})</span>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Lịch sử trạng thái</h2>
        <ul class="muted">
            @foreach ($vendorOrder->statusHistories as $hist)
                <li>
                    [{{ $hist->created_at }}]
                    <code>{{ $hist->from_status ?? 'khởi tạo' }}</code> &rarr; <strong><code>{{ $hist->to_status }}</code></strong>
                    @if ($hist->note) — {{ $hist->note }} @endif
                </li>
            @endforeach
        </ul>
    </div>
@endsection

