@extends('layouts.app')

@section('title', 'Chi tiết kỳ đối soát #' . $settlement->id)

@section('content')
    <div style="margin-bottom: 1rem;">
        <a href="{{ route('vendor.settlements.index') }}">&larr; Quay lại danh sách kỳ đối soát</a>
    </div>

    <div class="card">
        <h1>Kỳ đối soát #{{ $settlement->id }} ({{ $settlement->period_start->format('d/m/Y') }} &rarr; {{ $settlement->period_end->format('d/m/Y') }})</h1>
        <p class="muted">
            Trạng thái: <span class="badge {{ $settlement->status === 'paid' ? 'badge-ok' : 'badge-warn' }}">{{ strtoupper($settlement->status) }}</span>
            @if ($settlement->paid_at)
                · Đã chi trả lúc: {{ $settlement->paid_at->format('d/m/Y H:i') }}
            @endif
        </p>

        <div class="grid-kpi" style="margin-top: 1rem; margin-bottom: 0;">
            <div class="kpi-box">
                <div class="muted">Tổng Gross Sales</div>
                <div class="kpi-value">{{ number_format($settlement->gross_sales, 0, ',', '.') }} ₫</div>
            </div>
            <div class="kpi-box">
                <div class="muted">Tổng Hoa hồng</div>
                <div class="kpi-value">{{ number_format($settlement->commission_total, 0, ',', '.') }} ₫</div>
            </div>
            <div class="kpi-box">
                <div class="muted">Net Payout thực nhận</div>
                <div class="kpi-value" style="color: var(--ok);">{{ number_format($settlement->net_payout, 0, ',', '.') }} ₫</div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Các đơn hàng trong kỳ đối soát</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mã kiện hàng</th>
                        <th>Mã đơn cha</th>
                        <th>Gross</th>
                        <th>Hoa hồng</th>
                        <th>Net thực nhận</th>
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

