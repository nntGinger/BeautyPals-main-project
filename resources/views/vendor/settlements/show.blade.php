@extends('layouts.app')

@section('title', 'Chi tiết kỳ đối soát #' . $settlement->id)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/vendor-operations.css') }}">
@endpush

@section('content')
    <div class="operations-heading">
        <div>
            <h1>Chi tiết kỳ đối soát #{{ $settlement->id }}</h1>
            <p class="muted">
                Từ {{ $settlement->period_start?->format('d/m/Y') }} đến {{ $settlement->period_end?->format('d/m/Y') }}
                · Trạng thái:
                <span class="order-status {{ $settlement->status === 'paid' ? 'order-status-completed' : 'order-status-pending' }}">
                    {{ strtoupper($settlement->status) }}
                </span>
                @if ($settlement->paid_at)
                    · Đã thanh toán: {{ $settlement->paid_at->format('d/m/Y H:i') }}
                @endif
            </p>
        </div>
        <a class="btn btn-ghost" href="{{ route('vendor.settlements.index') }}">← Danh sách kỳ đối soát</a>
    </div>

    <div class="inventory-stats">
        <div>
            <small>Gross Sales</small>
            <strong class="money">{{ number_format((int) $settlement->gross_sales, 0, ',', '.') }} ₫</strong>
        </div>
        <div>
            <small>Hoa hồng sàn</small>
            <strong class="money">{{ number_format((int) $settlement->commission_total, 0, ',', '.') }} ₫</strong>
        </div>
        <div>
            <small>Net Payout thực nhận</small>
            <strong class="money">{{ number_format((int) $settlement->net_payout, 0, ',', '.') }} ₫</strong>
        </div>
    </div>

    <section class="operations-panel">
        <h2>Chi tiết từng đơn hàng trong kỳ</h2>
        <div class="operations-table-wrap">
            <table class="operations-table">
                <thead>
                    <tr>
                        <th>Mã kiện hàng</th>
                        <th>Mã đơn cha</th>
                        <th>Gross</th>
                        <th>Hoa hồng</th>
                        <th>Hoàn tiền</th>
                        <th>Net thực nhận</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($settlement->lines as $line)
                        <tr>
                            <td><code>{{ $line->vendorOrder?->vo_code }}</code></td>
                            <td><code>{{ $line->vendorOrder?->order?->order_code }}</code></td>
                            <td class="money">{{ number_format((int) $line->gross, 0, ',', '.') }} ₫</td>
                            <td class="money">{{ number_format((int) $line->commission, 0, ',', '.') }} ₫</td>
                            <td class="money">{{ number_format((int) $line->refund, 0, ',', '.') }} ₫</td>
                            <td class="money"><strong>{{ number_format((int) $line->net, 0, ',', '.') }} ₫</strong></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="operations-empty">Không có dòng đơn hàng trong kỳ này.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

