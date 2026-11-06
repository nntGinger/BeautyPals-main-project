@extends('layouts.app')

@section('title', 'Khu vực cửa hàng')

@section('content')
    <div class="card">
        <h1>{{ $vendor->store_name }}</h1>
        <p class="muted">Cửa hàng đã được duyệt. Đây là khu vực Vendor.</p>
        <p>Chủ sở hữu: {{ $user->name }} ({{ $user->email }})</p>

        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem;">
            <a href="{{ route('vendor.products.index') }}" class="btn">Quản lý sản phẩm ({{ $productsCount }})</a>
            <a href="{{ route('vendor.inventory.index') }}" class="btn btn-ghost">Kho & Lô hàng</a>
            <a href="{{ route('vendor.orders.index') }}" class="btn btn-ghost">Đơn hàng ({{ $pendingOrdersCount }} chờ xác nhận)</a>
            <a href="{{ route('vendor.settlements.index') }}" class="btn btn-ghost">Kỳ đối soát & Payout</a>
        </div>
    </div>

    <div class="grid-kpi">
        <div class="kpi-box">
            <div class="muted">Doanh thu gộp (Đơn hoàn tất)</div>
            <div class="kpi-value">{{ number_format($grossRevenue, 0, ',', '.') }} ₫</div>
        </div>
        <div class="kpi-box">
            <div class="muted">Hoa hồng sàn đã khấu trừ</div>
            <div class="kpi-value">{{ number_format($totalCommission, 0, ',', '.') }} ₫</div>
        </div>
        <div class="kpi-box">
            <div class="muted">Doanh thu thuần thực nhận</div>
            <div class="kpi-value">{{ number_format($netRevenue, 0, ',', '.') }} ₫</div>
        </div>
        <div class="kpi-box">
            <div class="muted">Lô sắp hết hạn (≤ 60 ngày)</div>
            <div class="kpi-value">{{ $expiringBatchesCount }}</div>
        </div>
    </div>

    <div class="form-grid">
        <div class="card">
            <h2>Doanh thu theo ngày hoàn tất</h2>
            @if ($dailyRevenue->isEmpty())
                <p class="muted">Chưa có dữ liệu đơn hoàn tất.</p>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Ngày</th>
                                <th>Số đơn</th>
                                <th>Doanh thu gộp</th>
                                <th>Thực nhận</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($dailyRevenue as $row)
                                <tr>
                                    <td>{{ $row->completed_date }}</td>
                                    <td>{{ $row->orders_count }}</td>
                                    <td>{{ number_format((int) $row->gross, 0, ',', '.') }} ₫</td>
                                    <td><strong>{{ number_format((int) $row->net, 0, ',', '.') }} ₫</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="card">
            <h2>Top sản phẩm bán chạy</h2>
            @if ($topProducts->isEmpty())
                <p class="muted">Chưa có sản phẩm bán ra ở trạng thái hoàn tất.</p>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Sản phẩm</th>
                                <th>SKU</th>
                                <th>SL bán</th>
                                <th>Doanh thu</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($topProducts as $tp)
                                <tr>
                                    <td><strong>{{ $tp->product_name }}</strong></td>
                                    <td><code>{{ $tp->sku_code }}</code></td>
                                    <td>{{ $tp->total_qty }}</td>
                                    <td>{{ number_format((int) $tp->total_revenue, 0, ',', '.') }} ₫</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
