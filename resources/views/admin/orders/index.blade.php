@extends('layouts.app')

@section('title', 'Giám sát đơn hàng toàn sàn — BeautyPals')

@section('content')
    <div class="card">
        <h1>Giám sát Đơn hàng Toàn Hệ thống</h1>
        <form method="GET" action="{{ route('admin.orders.index') }}" class="form-grid" style="margin-top: 1rem;">
            <div>
                <label for="vendor_id">Gian hàng</label>
                <select id="vendor_id" name="vendor_id">
                    <option value="">-- Tất cả gian hàng --</option>
                    @foreach ($vendors as $v)
                        <option value="{{ $v->id }}" @selected($vendorId === $v->id)>{{ $v->store_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status">Trạng thái</label>
                <select id="status" name="status">
                    <option value="">Tất cả</option>
                    @foreach (['pending', 'confirmed', 'packed', 'shipped', 'delivered', 'completed', 'cancelled', 'rejected'] as $st)
                        <option value="{{ $st }}" @selected($status === $st)>{{ strtoupper($st) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="from_date">Từ ngày</label>
                <input id="from_date" type="date" name="from_date" value="{{ $fromDate }}">
            </div>
            <div>
                <label for="to_date">Đến ngày</label>
                <input id="to_date" type="date" name="to_date" value="{{ $toDate }}">
            </div>
            <div style="display: flex; align-items: flex-end; gap: 0.5rem;">
                <button type="submit">Lọc đơn</button>
                <a href="{{ route('admin.orders.index') }}" class="btn btn-ghost">Xóa lọc</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mã kiện hàng</th>
                        <th>Mã đơn cha</th>
                        <th>Gian hàng</th>
                        <th>Khách hàng</th>
                        <th>Tổng tiền</th>
                        <th>Hoa hồng</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th>Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vendorOrders as $vo)
                        <tr>
                            <td><strong><code>{{ $vo->vo_code }}</code></strong></td>
                            <td><code>{{ $vo->order?->order_code }}</code></td>
                            <td>{{ $vo->vendor?->store_name }}</td>
                            <td>{{ $vo->order?->receiver }}</td>
                            <td><strong>{{ number_format($vo->total, 0, ',', '.') }} ₫</strong></td>
                            <td>{{ number_format($vo->commission_amount, 0, ',', '.') }} ₫</td>
                            <td><span class="badge">{{ strtoupper($vo->status) }}</span></td>
                            <td>{{ $vo->created_at?->format('d/m/Y H:i') }}</td>
                            <td><a href="{{ route('admin.orders.show', $vo) }}" class="btn btn-sm">Xem</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="muted">Không có đơn hàng nào khớp bộ lọc.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $vendorOrders->links() }}
    </div>
@endsection

