@extends('layouts.app')

@section('title', 'Đối soát & Thanh toán Vendor — BeautyPals')

@section('content')
    <div class="card">
        <h1>Quản lý Đối soát & Chi trả (Settlements)</h1>
        <p class="muted">Tổng hợp các đơn hàng đã hoàn tất (Completed) trong kỳ và chưa đối soát để tính Gross, Hoa hồng và Net Payout cho từng Vendor.</p>

        <form method="POST" action="{{ route('admin.settlements.generate') }}" novalidate>
            @csrf
            <div class="form-grid">
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
                <div style="display: flex; align-items: flex-end;">
                    <button type="submit">Chốt kỳ đối soát</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Danh sách các kỳ đối soát</h2>
        <div class="table-wrap">
            <table class="data-table">
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
                            <td>{{ $st->period_start->format('d/m/Y') }} &rarr; {{ $st->period_end->format('d/m/Y') }}</td>
                            <td>{{ $st->lines_count }}</td>
                            <td>{{ number_format($st->gross_sales, 0, ',', '.') }} ₫</td>
                            <td>{{ number_format($st->commission_total, 0, ',', '.') }} ₫</td>
                            <td><strong style="color: var(--ok);">{{ number_format($st->net_payout, 0, ',', '.') }} ₫</strong></td>
                            <td>
                                <span class="badge {{ $st->status === 'paid' ? 'badge-ok' : 'badge-warn' }}">
                                    {{ strtoupper($st->status) }}
                                </span>
                            </td>
                            <td style="display: flex; gap: 0.4rem;">
                                <a href="{{ route('admin.settlements.show', $st) }}" class="btn btn-sm">Chi tiết</a>
                                @if ($st->status !== 'paid')
                                    <form method="POST" action="{{ route('admin.settlements.pay', $st) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-ghost btn-sm">Đánh dấu đã chi</button>
                                    </form>
                                @endif
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

