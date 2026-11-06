@extends('layouts.app')

@section('title', 'Kỳ đối soát của gian hàng — BeautyPals')

@section('content')
    <div class="card">
        <h1>Kỳ đối soát & Thanh toán (Settlements)</h1>
        <p class="muted">Danh sách các kỳ đối soát doanh thu từ các đơn hàng đã hoàn tất (Completed) của gian hàng.</p>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mã kỳ</th>
                        <th>Từ ngày</th>
                        <th>Đến ngày</th>
                        <th>Số đơn</th>
                        <th>Doanh thu gộp (Gross)</th>
                        <th>Hoa hồng sàn</th>
                        <th>Thực nhận (Net Payout)</th>
                        <th>Trạng thái</th>
                        <th>Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($settlements as $st)
                        <tr>
                            <td>#{{ $st->id }}</td>
                            <td>{{ $st->period_start->format('d/m/Y') }}</td>
                            <td>{{ $st->period_end->format('d/m/Y') }}</td>
                            <td>{{ $st->lines_count }}</td>
                            <td>{{ number_format($st->gross_sales, 0, ',', '.') }} ₫</td>
                            <td>{{ number_format($st->commission_total, 0, ',', '.') }} ₫</td>
                            <td><strong style="color: var(--ok);">{{ number_format($st->net_payout, 0, ',', '.') }} ₫</strong></td>
                            <td>
                                <span class="badge {{ $st->status === 'paid' ? 'badge-ok' : 'badge-warn' }}">
                                    {{ strtoupper($st->status) }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('vendor.settlements.show', $st) }}" class="btn btn-sm">Xem dòng đối soát</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="muted">Chưa có kỳ đối soát nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $settlements->links() }}
    </div>
@endsection

