@extends('layouts.app')

@section('title', 'Đối soát & Chi trả Vendor — BeautyPals')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/vendor-operations.css') }}">
@endpush

@section('content')
    <div class="operations-heading">
        <div>
            <h1>Quản lý đối soát &amp; Chi trả (Settlements)</h1>
            <p class="muted">Tổng hợp các kiện hàng đã hoàn tất trong kỳ chưa đối soát, tính Gross, Hoa hồng sàn và Net Payout.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('admin.dashboard') }}">← Bảng điều khiển Admin</a>
    </div>

    <section class="operations-panel">
        <h2>Tạo / Cập nhật kỳ đối soát</h2>

        @if (! $settlementServiceReady)
            <div class="flash-error" role="status">
                Dịch vụ tính đối soát tự động <code>SettlementService</code> (<code>S4-08</code>) đang chờ hoàn tất tích hợp. Giao diện danh sách và chi tiết vẫn sẵn sàng tra cứu.
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settlements.generate') }}" class="operations-filter" novalidate>
            @csrf
            <div>
                <label for="period_start">Từ ngày (Period Start)</label>
                <input id="period_start" type="date" name="period_start" value="{{ old('period_start', now()->startOfMonth()->toDateString()) }}" required>
                @error('period_start') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="period_end">Đến ngày (Period End)</label>
                <input id="period_end" type="date" name="period_end" value="{{ old('period_end', now()->toDateString()) }}" required>
                @error('period_end') <p class="error">{{ $message }}</p> @enderror
            </div>
            <button type="submit">Chốt kỳ đối soát</button>
        </form>
    </section>

    <section class="operations-panel">
        <form method="GET" action="{{ route('admin.settlements.index') }}" class="operations-filter">
            <div>
                <label for="status">Lọc trạng thái kỳ</label>
                <select id="status" name="status">
                    <option value="">Tất cả trạng thái</option>
                    @foreach (['draft' => 'Draft', 'pending' => 'Pending', 'finalized' => 'Finalized', 'paid' => 'Paid'] as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit">Lọc</button>
            <a href="{{ route('admin.settlements.index') }}">Đặt lại</a>
        </form>

        <div class="operations-table-wrap">
            <table class="operations-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Gian hàng</th>
                        <th>Kỳ đối soát</th>
                        <th>Số đơn</th>
                        <th>Gross Sales</th>
                        <th>Hoa hồng sàn</th>
                        <th>Net Payout</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($settlements as $st)
                        <tr>
                            <td>#{{ $st->id }}</td>
                            <td><strong>{{ $st->vendor?->store_name }}</strong></td>
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
                            <td style="display:flex;gap:.5rem;flex-wrap:wrap;">
                                <a class="btn btn-ghost" href="{{ route('admin.settlements.show', $st) }}">Chi tiết</a>
                                @if ($st->status !== 'paid')
                                    <form method="POST" action="{{ route('admin.settlements.pay', $st) }}">
                                        @csrf
                                        <button type="submit">Đánh dấu đã chi</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="operations-empty">Chưa có kỳ đối soát nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $settlements->links('vendor.partials.pagination') }}
    </section>
@endsection

