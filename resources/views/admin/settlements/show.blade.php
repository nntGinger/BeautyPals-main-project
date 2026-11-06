@extends('layouts.app')

@section('title', 'Chi tiết kỳ đối soát #' . $settlement->id)

@section('content')
    <div style="margin-bottom: 1rem;">
        <a href="{{ route('admin.settlements.index') }}">&larr; Quay lại danh sách đối soát</a>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1>Kỳ đối soát #{{ $settlement->id }} — {{ $settlement->vendor?->store_name }}</h1>
                <p class="muted">
                    Kỳ: {{ $settlement->period_start->format('d/m/Y') }} &rarr; {{ $settlement->period_end->format('d/m/Y') }}
                    · Trạng thái: <span class="badge">{{ strtoupper($settlement->status) }}</span>
                </p>
            </div>
            @if ($settlement->status !== 'paid')
                <form method="POST" action="{{ route('admin.settlements.pay', $settlement) }}">
                    @csrf
                    <button type="submit">Xác nhận đã chi trả (Mark Paid)</button>
                </form>
            @endif
        </div>

        <div class="grid-kpi" style="margin-top: 1rem; margin-bottom: 0;">
            <div class="kpi-box">
                <div class="muted">Gross Sales</div>
                <div class="kpi-value">{{ number_format($settlement->gross_sales, 0, ',', '.') }} ₫</div>
            </div>
            <div class="kpi-box">
                <div class="muted">Hoa hồng sàn</div>
                <div class="kpi-value">{{ number_format($settlement->commission_total, 0, ',', '.') }} ₫</div>
            </div>
            <div class="kpi-box">
                <div class="muted">Net Payout</div>
                <div class="kpi-value" style="color: var(--ok);">{{ number_format($settlement->net_payout, 0, ',', '.') }} ₫</div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Chi tiết từng đơn hàng trong kỳ</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mã kiện hàng</th>
                        <th>Mã đơn cha</th>
                        <th>Gross</th>
                        <th>Hoa hồng</th>
                        <th>Net Payout</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($settlement->lines as $line)
                        <tr>
                            <td><code>{{ $line->vendorOrder?->vo_code }}</code></td>
                            <td><code>{{ $line->vendorOrder?->order?->order_code }}</code></td>
                            <td>{{ number_format($line->gross, 0, ',', '.') }} ₫</td>
                            <td>{{ number_format($line->commission, 0, ',', '.') }} ₫</td>
                            <td><strong>{{ number_format($line->net, 0, ',', '.') }} ₫</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

