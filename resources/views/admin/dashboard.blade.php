@extends('layouts.app')

@section('title', 'Khu vực quản trị')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/vendor-operations.css') }}">
@endpush

@section('content')
    <div class="operations-heading">
        <div>
            <h1>Bảng điều khiển Admin</h1>
            <p class="muted">Chỉ vai trò admin mới vào được /admin. Giám sát KPI toàn sàn, đơn hàng, tài khoản và đối soát.</p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <a class="btn" href="{{ route('admin.orders.index') }}">Đơn hàng toàn sàn</a>
            <a class="btn btn-ghost" href="{{ route('admin.users.index') }}">Tài khoản ({{ $userCount }})</a>
            <a class="btn btn-ghost" href="{{ route('admin.settlements.index') }}">Đối soát</a>
            <a class="btn btn-ghost" href="{{ route('admin.audit-logs.index') }}">Audit Logs</a>
            <a class="btn btn-ghost" href="{{ route('admin.vendors.index') }}">Vendor ({{ $vendorCount }})</a>
        </div>
    </div>

    <div class="inventory-stats">
        <div>
            <small>GMV Toàn Sàn (Hợp lệ)</small>
            <strong class="money">{{ number_format($gmv, 0, ',', '.') }} ₫</strong>
        </div>
        <div>
            <small>Doanh thu hoa hồng Platform</small>
            <strong class="money">{{ number_format($platformRevenue, 0, ',', '.') }} ₫</strong>
        </div>
        <div>
            <small>Tổng đơn hàng cha / Kiện hợp lệ</small>
            <strong>{{ $orderCount }} / {{ $activeVendorOrdersCount }}</strong>
        </div>
    </div>

    <section class="operations-panel">
        <h2>Chỉ số vận hành nhanh</h2>
        <ul>
            <li>Người dùng: {{ $userCount }} (Đang khóa: {{ $lockedUsersCount }})</li>
            <li>Cửa hàng: {{ $vendorCount }}</li>
            <li>Vendor chờ duyệt: {{ $pendingVendors }}</li>
            <li>Sản phẩm chờ duyệt: {{ $pendingProductsCount }}</li>
        </ul>
    </section>

    <section class="operations-panel">
        <h2>Hiệu suất theo Gian hàng (Top 10 GMV)</h2>
        <div class="operations-table-wrap">
            <table class="operations-table">
                <thead>
                    <tr>
                        <th>Gian hàng</th>
                        <th>Trạng thái</th>
                        <th>Tổng kiện</th>
                        <th>Kiện hợp lệ</th>
                        <th>GMV</th>
                        <th>Hoa hồng sàn</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vendorKpis as $v)
                        <tr>
                            <td><a href="{{ route('admin.vendors.show', $v) }}"><strong>{{ $v->store_name }}</strong></a></td>
                            <td>{{ strtoupper($v->status) }}</td>
                            <td>{{ $v->total_orders_count }}</td>
                            <td>{{ $v->active_orders_count }}</td>
                            <td class="money"><strong>{{ number_format((int) $v->active_gmv, 0, ',', '.') }} ₫</strong></td>
                            <td class="money">{{ number_format((int) $v->active_commission, 0, ',', '.') }} ₫</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="operations-empty">Chưa có gian hàng nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="operations-panel">
        <h2>Đơn hàng gần đây trên hệ thống</h2>
        <div class="operations-table-wrap">
            <table class="operations-table">
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
                    @forelse ($recentVendorOrders as $vo)
                        <tr>
                            <td><code>{{ $vo->vo_code }}</code></td>
                            <td>{{ $vo->vendor?->store_name }}</td>
                            <td>{{ $vo->order?->receiver }}</td>
                            <td class="money">{{ number_format((int) $vo->total, 0, ',', '.') }} ₫</td>
                            <td class="money">{{ number_format((int) $vo->commission_amount, 0, ',', '.') }} ₫</td>
                            <td><x-order-status :status="$vo->status" /></td>
                            <td><a class="btn btn-ghost" href="{{ route('admin.orders.show', $vo) }}">Xem</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="operations-empty">Chưa có đơn hàng nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
