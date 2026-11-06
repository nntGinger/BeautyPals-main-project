@extends('layouts.app')

@section('title', 'Khu vực quản trị')

@section('content')
    <div class="card">
        <h1>Bảng điều khiển Admin</h1>
        <p class="muted">Chỉ vai trò admin mới vào được /admin.</p>
        <ul>
            <li>Người dùng: {{ $userCount }}</li>
            <li>Cửa hàng: {{ $vendorCount }}</li>
            <li>Vendor chờ duyệt: {{ $pendingVendors }}</li>
        </ul>
    </div>

    <div class="grid-kpi">
        <div class="kpi-box">
            <div class="muted">GMV Toàn Sàn (Đơn hoàn tất)</div>
            <div class="kpi-value">{{ number_format($gmv, 0, ',', '.') }} ₫</div>
        </div>
        <div class="kpi-box">
            <div class="muted">Doanh thu Hoa hồng Platform</div>
            <div class="kpi-value">{{ number_format($platformRevenue, 0, ',', '.') }} ₫</div>
        </div>
        <div class="kpi-box">
            <div class="muted">Tổng đơn cha / Đơn vendor hoàn tất</div>
            <div class="kpi-value">{{ $totalOrders }} / {{ $completedVendorOrdersCount }}</div>
        </div>
        <div class="kpi-box">
            <div class="muted">Sản phẩm chờ duyệt</div>
            <div class="kpi-value">{{ $pendingProductsCount }}</div>
        </div>
    </div>

    <div class="card">
        <h2>Hiệu suất các Gian hàng (Vendor KPIs)</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Gian hàng</th>
                        <th>Trạng thái</th>
                        <th>Tổng đơn</th>
                        <th>Đơn hoàn tất</th>
                        <th>GMV hoàn tất</th>
                        <th>Hoa hồng thu về</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vendorKpis as $v)
                        <tr>
                            <td>
                                <a href="{{ route('admin.vendors.show', $v) }}"><strong>{{ $v->store_name }}</strong></a>
                            </td>
                            <td><span class="badge">{{ strtoupper($v->status) }}</span></td>
                            <td>{{ $v->total_orders_count }}</td>
                            <td>{{ $v->completed_orders_count }}</td>
                            <td><strong>{{ number_format((int) $v->completed_gmv, 0, ',', '.') }} ₫</strong></td>
                            <td>{{ number_format((int) $v->completed_commission, 0, ',', '.') }} ₫</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Đơn hàng mới nhất trên hệ thống</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mã kiện hàng</th>
                        <th>Gian hàng</th>
                        <th>Khách hàng</th>
                        <th>Tổng tiền</th>
                        <th>Hoa hồng</th>
                        <th>Trạng thái</th>
                        <th>Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentVendorOrders as $vo)
                        <tr>
                            <td><code>{{ $vo->vo_code }}</code></td>
                            <td>{{ $vo->vendor?->store_name }}</td>
                            <td>{{ $vo->order?->receiver }}</td>
                            <td>{{ number_format($vo->total, 0, ',', '.') }} ₫</td>
                            <td>{{ number_format($vo->commission_amount, 0, ',', '.') }} ₫</td>
                            <td><span class="badge">{{ strtoupper($vo->status) }}</span></td>
                            <td><a href="{{ route('admin.orders.show', $vo) }}" class="btn btn-sm">Xem</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
