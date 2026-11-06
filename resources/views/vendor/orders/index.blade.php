@extends('layouts.app')

@section('title', 'Quản lý đơn hàng gian hàng — BeautyPals')

@section('content')
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1>Đơn hàng của gian hàng</h1>
                <p class="muted">Xử lý đơn hàng theo đúng quy trình máy trạng thái (Pending &rarr; Confirmed &rarr; Packed &rarr; Shipped &rarr; Delivered).</p>
            </div>
            <form method="GET" action="{{ route('vendor.orders.index') }}" style="display: flex; gap: 0.5rem; align-items: center;">
                <label for="status" style="margin: 0;">Trạng thái:</label>
                <select id="status" name="status" style="width: auto;">
                    <option value="">Tất cả</option>
                    @foreach (['pending', 'confirmed', 'packed', 'shipped', 'delivered', 'completed', 'cancelled', 'rejected'] as $st)
                        <option value="{{ $st }}" @selected($status === $st)>{{ strtoupper($st) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm">Lọc</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mã kiện hàng</th>
                        <th>Mã đơn cha</th>
                        <th>Khách hàng</th>
                        <th>Doanh thu gộp</th>
                        <th>Hoa hồng sàn</th>
                        <th>Trạng thái</th>
                        <th>Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vendorOrders as $vo)
                        <tr>
                            <td><strong><code>{{ $vo->vo_code }}</code></strong></td>
                            <td><code>{{ $vo->order?->order_code }}</code></td>
                            <td>{{ $vo->order?->receiver }} ({{ $vo->order?->phone }})</td>
                            <td><strong>{{ number_format($vo->total, 0, ',', '.') }} ₫</strong></td>
                            <td>{{ number_format($vo->commission_amount, 0, ',', '.') }} ₫</td>
                            <td><span class="badge">{{ strtoupper($vo->status) }}</span></td>
                            <td>
                                <a href="{{ route('vendor.orders.show', $vo) }}" class="btn btn-sm">Xử lý đơn</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted">Không có đơn hàng nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1rem;">
            {{ $vendorOrders->links() }}
        </div>
    </div>
@endsection

