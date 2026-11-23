
@extends('layouts.app')

@section('title', 'Tổng quan cửa hàng | BeautyPals')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/vendor-operations.css') }}">

    <style>
        .vendor-dashboard {
            color: #322a30;
        }

        .vendor-dashboard *,
        .vendor-dashboard *::before,
        .vendor-dashboard *::after {
            box-sizing: border-box;
        }

        .vendor-dashboard__header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .vendor-dashboard__eyebrow {
            margin: 0 0 .4rem;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #a04465;
        }

        .vendor-dashboard__header h1 {
            margin: 0 0 .5rem;
            font-size: clamp(1.5rem, 3vw, 2rem);
            overflow-wrap: anywhere;
        }

        .vendor-dashboard__description {
            margin: 0;
            color: #776d73;
            line-height: 1.6;
        }

        .vendor-dashboard__badge {
            display: inline-flex;
            padding: .5rem .8rem;
            border-radius: 999px;
            background: #e5f4eb;
            color: #236542;
            font-size: .8rem;
            font-weight: 700;
        }

        .vendor-dashboard__actions {
            display: flex;
            flex-wrap: wrap;
            gap: .65rem;
            margin-bottom: 1.75rem;
        }

        .vendor-dashboard__section-title {
            margin: 0 0 .4rem;
            font-size: 1.15rem;
        }

        .vendor-dashboard__section-description {
            margin: 0 0 1rem;
            color: #776d73;
            font-size: .9rem;
        }

        .vendor-dashboard .grid-kpi {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1.75rem;
        }

        .vendor-dashboard .kpi-box {
            min-width: 0;
            padding: 1.4rem;
            background: #fff;
            border: 1px solid #eadce2;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(75, 38, 55, .04);
        }

        .vendor-dashboard .kpi-box--primary {
            color: #fff;
            background: linear-gradient(135deg, #ad5576, #743850);
            border-color: #ad5576;
        }

        .vendor-dashboard .kpi-box__label {
            display: block;
            font-size: .82rem;
            font-weight: 600;
            color: #796e75;
        }

        .vendor-dashboard .kpi-box--primary .kpi-box__label,
        .vendor-dashboard .kpi-box--primary .kpi-box__note {
            color: #f8e8ee;
        }

        .vendor-dashboard .kpi-box__value {
            display: block;
            margin-top: 1rem;
            font-size: clamp(1.2rem, 2vw, 1.6rem);
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            overflow-wrap: anywhere;
        }

        .vendor-dashboard .kpi-box__note {
            margin: .65rem 0 0;
            color: #796e75;
            font-size: .77rem;
            line-height: 1.5;
        }

        .vendor-dashboard__panel {
            padding: 1.4rem;
            margin-bottom: 1.5rem;
            background: #fff;
            border: 1px solid #eadce2;
            border-radius: 16px;
        }

        .vendor-dashboard__panel-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .vendor-dashboard__panel-header h2 {
            margin: 0;
            font-size: 1.15rem;
        }

        .vendor-dashboard__panel-header p {
            margin: .4rem 0 0;
            color: #776d73;
            font-size: .86rem;
        }

        .vendor-dashboard .operations-table th {
            background: #fcf6f8;
        }

        .vendor-dashboard .operations-table a {
            color: #a04465;
        }

        .vendor-dashboard__empty {
            padding: 2rem 1rem !important;
            text-align: center;
            color: #776d73;
        }

        .vendor-dashboard__info {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }

        .vendor-dashboard__info-item {
            min-width: 0;
            padding: 1rem;
            background: #fcf8fa;
            border-radius: 12px;
        }

        .vendor-dashboard__info-item span {
            display: block;
            margin-bottom: .4rem;
            color: #796e75;
            font-size: .8rem;
        }

        .vendor-dashboard__info-item strong {
            overflow-wrap: anywhere;
        }

        @media (max-width: 1050px) {
            .vendor-dashboard .grid-kpi {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .vendor-dashboard .grid-kpi,
            .vendor-dashboard__info {
                grid-template-columns: 1fr;
            }

            .vendor-dashboard__actions {
                flex-direction: column;
            }

            .vendor-dashboard__actions .btn {
                text-align: center;
            }

            .vendor-dashboard__panel {
                padding: 1rem;
            }
        }
    </style>
@endpush

@section('content')
    <div class="vendor-dashboard">
        {{-- Header --}}
        <header class="vendor-dashboard__header">
            <div>
                <p class="vendor-dashboard__eyebrow">
                    BeautyPals / Vendor Center
                </p>

                <h1>Tổng quan cửa hàng</h1>

                <p class="vendor-dashboard__description">
                    Xin chào, {{ $user->name }}.
                    Bạn đang quản lý gian hàng
                    <strong>{{ $vendor->store_name }}</strong>.
                </p>
            </div>

            @if ($vendor->status === 'approved')
                <span class="vendor-dashboard__badge">
                    Gian hàng đã duyệt
                </span>
            @endif
        </header>

        {{-- Điều hướng nhanh --}}
        <nav class="vendor-dashboard__actions" aria-label="Điều hướng cửa hàng">
            <a class="btn" href="{{ route('vendor.orders.index') }}">
                Quản lý đơn hàng
            </a>

            <a class="btn btn-ghost" href="{{ route('vendor.inventory.index') }}">
                Quản lý tồn kho
            </a>

            <a class="btn btn-ghost" href="{{ route('vendor.settlements.index') }}">
                Đối soát doanh thu
            </a>
        </nav>

        {{-- KPI --}}
        <section aria-labelledby="vendor-kpi-heading">
            <h2 id="vendor-kpi-heading" class="vendor-dashboard__section-title">
                Tổng quan kinh doanh
            </h2>

            <p class="vendor-dashboard__section-description">
                Các chỉ số dựa trên kiện hàng hoàn tất và kỳ đối soát của cửa hàng.
            </p>

            <div class="grid-kpi">
                <article class="kpi-box kpi-box--primary">
                    <span class="kpi-box__label">Doanh thu sau hoa hồng</span>

                    <strong class="kpi-box__value">
                        {{ number_format((int) $netRevenue, 0, ',', '.') }} ₫
                    </strong>

                    <p class="kpi-box__note">
                        Từ các kiện hàng hoàn tất, chưa xét hoàn tiền
                    </p>
                </article>

                <article class="kpi-box">
                    <span class="kpi-box__label">Đơn hàng hoàn tất</span>

                    <strong class="kpi-box__value">
                        {{ number_format((int) $completedOrdersCount, 0, ',', '.') }}
                    </strong>

                    <p class="kpi-box__note">
                        Số kiện hàng đã hoàn tất
                    </p>
                </article>

                <article class="kpi-box">
                    <span class="kpi-box__label">Sản phẩm đã bán</span>

                    <strong class="kpi-box__value">
                        {{ number_format((int) $soldItemsCount, 0, ',', '.') }}
                    </strong>

                    <p class="kpi-box__note">
                        Tổng số lượng từ kiện hàng hoàn tất
                    </p>
                </article>

                <article class="kpi-box">
                    <span class="kpi-box__label">Chờ chi trả</span>

                    <strong class="kpi-box__value">
                        {{ number_format((int) $pendingPayout, 0, ',', '.') }} ₫
                    </strong>

                    <p class="kpi-box__note">
                        Tổng thực nhận của các kỳ đối soát pending
                    </p>
                </article>
            </div>
        </section>

        {{-- Top 5 sản phẩm --}}
        <section class="vendor-dashboard__panel"
                 aria-labelledby="vendor-top-products-heading">
            <div class="vendor-dashboard__panel-header">
                <div>
                    <h2 id="vendor-top-products-heading">
                        Top 5 sản phẩm bán chạy
                    </h2>
                    <p>Xếp hạng theo số lượng sản phẩm trong các kiện hoàn tất.</p>
                </div>
            </div>

            <div class="operations-table-wrap"
                 role="region"
                 aria-label="Bảng Top 5 sản phẩm"
                 tabindex="0">
                <table class="operations-table">
                    <thead>
                        <tr>
                            <th scope="col">Sản phẩm</th>
                            <th scope="col">Số lượng bán</th>
                            <th scope="col">Doanh thu dòng hàng</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topProducts as $product)
                            <tr>
                                <td>
                                    <strong>{{ $product->product_name }}</strong>
                                </td>

                                <td>
                                    {{ number_format((int) $product->sold_quantity, 0, ',', '.') }}
                                </td>

                                <td class="money">
                                    {{ number_format((int) $product->sales_amount, 0, ',', '.') }} ₫
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="vendor-dashboard__empty">
                                    Chưa có sản phẩm thuộc đơn hàng hoàn tất.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Đối soát --}}
        <section class="vendor-dashboard__panel"
                 aria-labelledby="vendor-settlements-heading">
            <div class="vendor-dashboard__panel-header">
                <div>
                    <h2 id="vendor-settlements-heading">
                        Đối soát doanh thu gần đây
                    </h2>
                    <p>Năm kỳ đối soát mới nhất của cửa hàng.</p>
                </div>

                <a class="btn btn-ghost"
                   href="{{ route('vendor.settlements.index') }}">
                    Xem tất cả
                </a>
            </div>

            <div class="operations-table-wrap"
                 role="region"
                 aria-label="Bảng đối soát gần đây"
                 tabindex="0">
                <table class="operations-table">
                    <thead>
                        <tr>
                            <th scope="col">Kỳ đối soát</th>
                            <th scope="col">Số đơn</th>
                            <th scope="col">Doanh thu gộp</th>
                            <th scope="col">Hoa hồng sàn</th>
                            <th scope="col">Thực nhận</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentSettlements as $settlement)
                            <tr>
                                <td>
                                    {{ $settlement->period_start?->format('d/m/Y') }}
                                    →
                                    {{ $settlement->period_end?->format('d/m/Y') }}
                                </td>

                                <td>
                                    {{ $settlement->lines_count }}
                                </td>

                                <td class="money">
                                    {{ number_format((int) $settlement->gross_sales, 0, ',', '.') }} ₫
                                </td>

                                <td class="money">
                                    {{ number_format((int) $settlement->commission_total, 0, ',', '.') }} ₫
                                </td>

                                <td class="money">
                                    <strong>
                                        {{ number_format((int) $settlement->net_payout, 0, ',', '.') }} ₫
                                    </strong>
                                </td>

                                <td>
                                    <span class="order-status {{ $settlement->status === 'paid' ? 'order-status-completed' : 'order-status-pending' }}">
                                        {{ strtoupper($settlement->status) }}
                                    </span>
                                </td>

                                <td>
                                    <a class="btn btn-ghost"
                                       href="{{ route('vendor.settlements.show', $settlement) }}">
                                        Xem
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="vendor-dashboard__empty">
                                    Gian hàng chưa có kỳ đối soát nào.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Thông tin cửa hàng --}}
        <section class="vendor-dashboard__panel"
                 aria-labelledby="vendor-info-heading">
            <div class="vendor-dashboard__panel-header">
                <div>
                    <h2 id="vendor-info-heading">Thông tin gian hàng</h2>
                    <p>Thông tin tài khoản và trạng thái cửa hàng.</p>
                </div>
            </div>

            <div class="vendor-dashboard__info">
                <div class="vendor-dashboard__info-item">
                    <span>Tên cửa hàng</span>
                    <strong>{{ $vendor->store_name }}</strong>
                </div>

                <div class="vendor-dashboard__info-item">
                    <span>Chủ sở hữu</span>
                    <strong>{{ $user->name }}</strong>
                </div>

                <div class="vendor-dashboard__info-item">
                    <span>Email</span>
                    <strong>{{ $user->email }}</strong>
                </div>

                <div class="vendor-dashboard__info-item">
                    <span>Trạng thái</span>
                    <strong>{{ strtoupper($vendor->status) }}</strong>
                </div>
            </div>
        </section>
    </div>
@endsection
