@extends('layouts.app')

@section('title', 'Giám sát đơn hàng toàn sàn — BeautyPals')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/vendor-operations.css') }}">
@endpush

@section('content')
    <div class="operations-heading">
        <div>
            <h1>Giám sát đơn hàng toàn hệ thống</h1>
            <p class="muted">Theo dõi đơn hàng đa gian hàng theo cửa hàng, trạng thái xử lý và khoảng thời gian.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('admin.dashboard') }}">← Bảng điều khiển Admin</a>
    </div>

    <section class="operations-panel">
        <form method="GET" action="{{ route('admin.orders.index') }}" class="operations-filter">
            <div>
                <label for="vendor_id">Gian hàng</label>
                <select id="vendor_id" name="vendor_id">
                    <option value="">Tất cả gian hàng</option>
                    @foreach ($vendors as $v)
                        <option value="{{ $v->id }}" @selected($vendorId === $v->id)>{{ $v->store_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status">Trạng thái</label>
                <select id="status" name="status">
                    <option value="">Tất cả</option>
                    @foreach ($statuses as $item)
                        <option value="{{ $item->value }}" @selected($status === $item->value)>{{ $item->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="from">Từ ngày</label>
                <input id="from" type="date" name="from" value="{{ $from }}">
            </div>
            <div>
                <label for="to">Đến ngày</label>
                <input id="to" type="date" name="to" value="{{ $to }}">
            </div>
            <button type="submit">Lọc đơn</button>
            <a href="{{ route('admin.orders.index') }}">Đặt lại</a>
        </form>

        <div class="operations-table-wrap">
            <table class="operations-table">
                <thead>
                    <tr>
                        <th>Mã kiện hàng</th>
                        <th>Đơn cha</th>
                        <th>Gian hàng</th>
                        <th>Khách hàng</th>
                        <th>Số dòng</th>
                        <th>Tổng tiền</th>
                        <th>Hoa hồng</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vendorOrders as $vo)
                        <tr>
                            <td><code>{{ $vo->vo_code }}</code></td>
                            <td><code>{{ $vo->order?->order_code }}</code></td>
                            <td>{{ $vo->vendor?->store_name }}</td>
                            <td>{{ $vo->order?->customer?->name ?? $vo->order?->receiver }}</td>
                            <td>{{ $vo->items_count }}</td>
                            <td class="money"><strong>{{ number_format((int) $vo->total, 0, ',', '.') }} ₫</strong></td>
                            <td class="money">{{ number_format((int) $vo->commission_amount, 0, ',', '.') }} ₫</td>
                            <td><x-order-status :status="$vo->status" /></td>
                            <td>{{ $vo->created_at?->format('d/m/Y H:i') }}</td>
                            <td><a class="btn btn-ghost" href="{{ route('admin.orders.show', $vo) }}">Chi tiết</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="operations-empty">Không có đơn hàng nào khớp bộ lọc.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $vendorOrders->links('vendor.partials.pagination') }}
    </section>
@endsection

