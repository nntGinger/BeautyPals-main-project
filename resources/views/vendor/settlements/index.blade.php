@extends('layouts.app')

@section('title', 'Đối soát doanh thu cửa hàng — BeautyPals')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/vendor-operations.css') }}">
@endpush

@section('content')
    <div class="operations-heading">
        <div>
            <h1>Đối soát &amp; Payout — {{ $vendor->store_name }}</h1>
            <p class="muted">Theo dõi các kỳ đối soát doanh thu, hoa hồng và trạng thái thanh toán của gian hàng {{ $vendor->store_name }}.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('vendor.dashboard') }}">← Khu vực Vendor</a>
    </div>

    <section class="operations-panel">
        <div class="operations-table-wrap">
            <table class="operations-table">
                <thead>
                    <tr>
                        <th>Kỳ đối soát</th>
                        <th>Số đơn</th>
                        <th>Gross Sales</th>
                        <th>Hoa hồng sàn</th>
                        <th>Net thực nhận</th>
                        <th>Trạng thái</th>
                        <th>Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($settlements as $st)
                        <tr>
                            <td>{{ $st->period_start?->format('d/m/Y') }} → {{ $st->period_end?->format('d/m/Y') }}</td>
                            <td>{{ $st->lines_count }}</td>
                            <td class="money">{{ number_format((int) $st->gross_sales, 0, ',', '.') }} ₫</td>
                            <td class="money">{{ number_format((int) $st->commission_total, 0, ',', '.') }} ₫</td>
                            <td class="money"><strong>{{ number_format((int) $st->net_payout, 0, ',', '.') }} ₫</strong></td>
                            <td>
                                <span class="order-status {{ $st->status === 'paid' ? 'order-status-completed' : 'order-status-pending' }}">
                                    {{ strtoupper($st->status) }}
                                </span>
                            </td>
                            <td><a class="btn btn-ghost" href="{{ route('vendor.settlements.show', $st) }}">Xem</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="operations-empty">Gian hàng chưa có kỳ đối soát nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $settlements->links('vendor.partials.pagination') }}
    </section>
@endsection
