@extends('layouts.app')

@section('title', 'Tổng quan quản trị | BeautyPals')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/vendor-operations.css') }}">

    <style>
        /* =========================================
           S4-10 - BeautyPals Admin Dashboard
           Sử dụng màu thương hiệu từ layouts/app
           ========================================= */

        .bp-admin-dashboard {
            color: var(--ink);
        }

        .bp-admin-dashboard *,
        .bp-admin-dashboard *::before,
        .bp-admin-dashboard *::after {
            box-sizing: border-box;
        }

        /* Header */
        .bp-admin-dashboard .admin-hero {
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding: 2rem;
            overflow: hidden;
            border-radius: 20px;
            background: linear-gradient(
                125deg,
                var(--accent-dark),
                var(--accent)
            );
            color: #fff;
        }

        .bp-admin-dashboard .admin-hero::after {
            content: "";
            position: absolute;
            right: -65px;
            bottom: -125px;
            width: 280px;
            height: 280px;
            border: 1px solid rgba(255, 255, 255, .25);
            border-radius: 50%;
            pointer-events: none;
        }

        .bp-admin-dashboard .admin-hero__content,
        .bp-admin-dashboard .admin-hero__actions {
            position: relative;
            z-index: 1;
        }

        .bp-admin-dashboard .admin-hero__eyebrow {
            display: block;
            margin-bottom: .6rem;
            color: #fff;
            opacity: .85;
            font-size: .76rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .bp-admin-dashboard .admin-hero h1 {
            margin: 0 0 .7rem;
            color: #fff;
            font-size: clamp(1.65rem, 3vw, 2.2rem);
            line-height: 1.2;
        }

        .bp-admin-dashboard .admin-hero__description {
            max-width: 610px;
            margin: 0;
            color: #fff;
            opacity: .94;
            line-height: 1.7;
        }

        .bp-admin-dashboard .admin-hero__actions {
            display: flex;
            flex-wrap: wrap;
            gap: .65rem;
        }

        .bp-admin-dashboard .admin-hero__link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: .75rem 1.1rem;
            border: 1px solid rgba(255, 255, 255, .8);
            border-radius: 999px;
            color: #fff;
            background: transparent;
            font-size: .88rem;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
        }

        .bp-admin-dashboard .admin-hero__link--primary {
            color: var(--accent-dark);
            background: var(--card);
            border-color: var(--card);
        }

        .bp-admin-dashboard .admin-hero__link:hover {
            background: rgba(255, 255, 255, .2);
            color: #fff;
            text-decoration: none;
        }

        .bp-admin-dashboard .admin-hero__link--primary:hover {
            color: var(--accent-dark);
            background: var(--bg);
        }

        /* Section headings */
        .bp-admin-dashboard .admin-section {
            margin-bottom: 1.7rem;
        }

        .bp-admin-dashboard .admin-section__heading {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .bp-admin-dashboard .admin-section__heading h2 {
            margin: 0;
            color: var(--ink);
            font-size: 1.2rem;
            font-weight: 750;
        }

        .bp-admin-dashboard .admin-section__heading p {
            margin: .4rem 0 0;
            color: var(--muted);
            font-size: .88rem;
            line-height: 1.55;
        }

        .bp-admin-dashboard .admin-section__tag {
            display: inline-flex;
            padding: .4rem .7rem;
            border: 1px solid var(--line);
            border-radius: 999px;
            color: var(--accent-dark);
            background: var(--card);
            font-size: .78rem;
            font-weight: 700;
        }

        /* Financial KPI cards */
        .bp-admin-dashboard .grid-kpi {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }

        .bp-admin-dashboard .kpi-box {
            min-width: 0;
            min-height: 170px;
            padding: 1.35rem;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--card);
            box-shadow: 0 6px 20px rgba(43, 33, 28, .04);
        }

        .bp-admin-dashboard .kpi-box--primary {
            background: linear-gradient(
                135deg,
                var(--accent),
                var(--accent-dark)
            );
            border-color: var(--accent);
            color: #fff;
        }

        .bp-admin-dashboard .kpi-box__top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: .6rem;
        }

        .bp-admin-dashboard .kpi-box__label {
            display: block;
            color: var(--muted);
            font-size: .83rem;
            font-weight: 600;
            line-height: 1.45;
        }

        .bp-admin-dashboard .kpi-box__symbol {
            display: grid;
            place-items: center;
            flex: 0 0 34px;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: var(--bg);
            color: var(--accent-dark);
            font-size: 1rem;
            font-weight: 800;
        }

        .bp-admin-dashboard .kpi-box--primary .kpi-box__label,
        .bp-admin-dashboard .kpi-box--primary .kpi-box__note {
            color: #fff;
        }

        .bp-admin-dashboard .kpi-box--primary .kpi-box__symbol {
            background: rgba(255, 255, 255, .18);
            color: #fff;
        }

        .bp-admin-dashboard .kpi-box__value {
            display: block;
            margin-top: 1.15rem;
            color: var(--ink);
            font-size: clamp(1.3rem, 2.2vw, 1.8rem);
            font-weight: 800;
            line-height: 1.3;
            overflow-wrap: anywhere;
            font-variant-numeric: tabular-nums;
        }

        .bp-admin-dashboard .kpi-box--primary .kpi-box__value {
            color: #fff;
        }

        .bp-admin-dashboard .kpi-box__note {
            margin: .6rem 0 0;
            color: var(--muted);
            font-size: .76rem;
            line-height: 1.5;
        }

        /* Shared panel */
        .bp-admin-dashboard .admin-panel {
            min-width: 0;
            padding: 1.4rem;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--card);
            box-shadow: 0 5px 18px rgba(43, 33, 28, .03);
        }

        .bp-admin-dashboard .admin-panel h3 {
            margin: 0;
            color: var(--ink);
            font-size: 1.08rem;
        }

        .bp-admin-dashboard .admin-panel__description {
            margin: .45rem 0 1.1rem;
            color: var(--muted);
            font-size: .85rem;
            line-height: 1.55;
        }

        /* Operational overview */
        .bp-admin-dashboard .admin-operations {
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(260px, 1fr);
            gap: 1rem;
        }

        .bp-admin-dashboard .admin-task-list {
            display: grid;
            gap: .75rem;
        }

        .bp-admin-dashboard .admin-task {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            min-width: 0;
            padding: .95rem 1rem;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--bg);
            color: var(--ink);
            text-decoration: none;
        }

        .bp-admin-dashboard .admin-task:hover {
            border-color: var(--accent);
            background: var(--card);
            text-decoration: none;
        }

        .bp-admin-dashboard .admin-task__title {
            display: block;
            font-size: .9rem;
            font-weight: 700;
        }

        .bp-admin-dashboard .admin-task__description {
            display: block;
            margin-top: .3rem;
            color: var(--muted);
            font-size: .76rem;
            line-height: 1.45;
        }

        .bp-admin-dashboard .admin-task__number {
            display: grid;
            place-items: center;
            flex: none;
            min-width: 38px;
            height: 38px;
            padding: 0 .55rem;
            border-radius: 10px;
            background: var(--card);
            color: var(--accent-dark);
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        .bp-admin-dashboard .admin-scale {
            display: grid;
            gap: .85rem;
        }

        .bp-admin-dashboard .admin-scale__item {
            padding: 1.15rem;
            border-radius: 12px;
            background: var(--bg);
        }

        .bp-admin-dashboard .admin-scale__label {
            color: var(--muted);
            font-size: .83rem;
        }

        .bp-admin-dashboard .admin-scale__value {
            display: block;
            margin-top: .5rem;
            color: var(--ink);
            font-size: 1.6rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        /* Tables */
        .bp-admin-dashboard .admin-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .bp-admin-dashboard .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .88rem;
            font-variant-numeric: tabular-nums;
        }

        .bp-admin-dashboard .admin-table th {
            padding: .9rem .8rem;
            background: var(--bg);
            color: var(--muted);
            font-size: .78rem;
            font-weight: 700;
            text-align: left;
            white-space: nowrap;
        }

        .bp-admin-dashboard .admin-table td {
            padding: 1rem .8rem;
            border-bottom: 1px solid var(--line);
            vertical-align: middle;
        }

        .bp-admin-dashboard .admin-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .bp-admin-dashboard .admin-table tbody tr:hover {
            background: var(--bg);
        }

        .bp-admin-dashboard .admin-table a:not(.btn) {
            color: var(--accent-dark);
            font-weight: 700;
        }

        .bp-admin-dashboard .admin-table .money {
            white-space: nowrap;
        }

        .bp-admin-dashboard .admin-vendor-name {
            display: flex;
            align-items: center;
            gap: .6rem;
        }

        .bp-admin-dashboard .admin-rank {
            display: grid;
            place-items: center;
            flex: 0 0 30px;
            width: 30px;
            height: 30px;
            border-radius: 9px;
            background: var(--bg);
            color: var(--accent-dark);
            font-size: .75rem;
            font-weight: 800;
        }

        .bp-admin-dashboard .admin-status {
            display: inline-block;
            padding: .35rem .65rem;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: var(--bg);
            color: var(--ink);
            font-size: .75rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .bp-admin-dashboard .admin-status--approved {
            background: #e5f4eb;
            border-color: #b7dfc8;
            color: #236542;
        }

        .bp-admin-dashboard .admin-status--pending {
            background: #fff2d9;
            border-color: #f0ddaf;
            color: #835617;
        }

        .bp-admin-dashboard .admin-status--rejected {
            background: #fde7e5;
            border-color: #efc7c3;
            color: var(--danger);
        }

        .bp-admin-dashboard .admin-gmv-track {
            width: 115px;
            max-width: 100%;
            height: 6px;
            margin-top: .55rem;
            overflow: hidden;
            border-radius: 999px;
            background: var(--line);
        }

        .bp-admin-dashboard .admin-gmv-fill {
            height: 100%;
            border-radius: 999px;
            background: var(--accent);
        }

        .bp-admin-dashboard .admin-empty {
            padding: 2rem 1rem !important;
            color: var(--muted);
            text-align: center;
        }

        .bp-admin-dashboard .admin-link {
            display: inline-block;
            padding: .55rem .85rem;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: var(--card);
            color: var(--accent-dark);
            font-size: .82rem;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
        }

        .bp-admin-dashboard .admin-link:hover {
            border-color: var(--accent);
            background: var(--bg);
            text-decoration: none;
        }

        .bp-admin-dashboard .admin-table-note {
            margin: 1rem 0 0;
            color: var(--muted);
            font-size: .79rem;
            line-height: 1.6;
        }

        /* Responsive */
        @media (max-width: 1100px) {
            .bp-admin-dashboard .grid-kpi {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 800px) {
            .bp-admin-dashboard .admin-operations {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .bp-admin-dashboard .admin-hero {
                padding: 1.4rem;
            }

            .bp-admin-dashboard .admin-hero__actions {
                width: 100%;
            }

            .bp-admin-dashboard .admin-hero__link {
                flex: 1 1 auto;
            }

            .bp-admin-dashboard .admin-panel {
                padding: 1rem;
            }

            .bp-admin-dashboard .kpi-box {
                min-height: 145px;
                padding: 1rem;
            }

            .bp-admin-dashboard .admin-table {
                min-width: 720px;
            }
        }

        @media (max-width: 390px) {
            .bp-admin-dashboard .grid-kpi {
                grid-template-columns: 1fr;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .bp-admin-dashboard *,
            .bp-admin-dashboard *::before,
            .bp-admin-dashboard *::after {
                scroll-behavior: auto;
            }
        }
    </style>
@endpush

@section('content')
    <div class="bp-admin-dashboard">

        {{-- 1. Header --}}
        <header class="admin-hero">
            <div class="admin-hero__content">
                <span class="admin-hero__eyebrow">
                    BeautyPals / Admin Dashboard
                </span>

                <h1>Tổng quan vận hành</h1>

                <p class="admin-hero__description">
                    Theo dõi hiệu quả kinh doanh, giám sát hoạt động
                    toàn sàn và nhanh chóng xử lý các công việc quản trị.
                </p>
            </div>

            <nav class="admin-hero__actions"
                 aria-label="Thao tác quản trị nhanh">
                <a class="admin-hero__link admin-hero__link--primary"
                   href="{{ route('admin.orders.index') }}">
                    Xem đơn hàng
                </a>

                <a class="admin-hero__link"
                   href="{{ route('admin.settlements.index') }}">
                    Đối soát
                </a>
            </nav>
        </header>

        {{-- 2. KPI toàn sàn --}}
        <section class="admin-section"
                 aria-labelledby="admin-kpi-heading">
            <div class="admin-section__heading">
                <div>
                    <h2 id="admin-kpi-heading">
                        Hiệu quả kinh doanh
                    </h2>

                    <p>
                        Tổng hợp từ dữ liệu giao dịch hiện có trên BeautyPals.
                    </p>
                </div>

                <span class="admin-section__tag">
                    Toàn hệ thống
                </span>
            </div>

            <div class="grid-kpi">
                <article class="kpi-box kpi-box--primary">
                    <div class="kpi-box__top">
                        <span class="kpi-box__label">
                            GMV toàn sàn
                        </span>

                        <span class="kpi-box__symbol" aria-hidden="true">
                            ₫
                        </span>
                    </div>

                    <strong class="kpi-box__value">
                        {{ number_format((int) $gmv, 0, ',', '.') }} ₫
                    </strong>

                    <p class="kpi-box__note">
                        Tổng subtotal, không tính kiện hủy hoặc từ chối
                    </p>
                </article>

                <article class="kpi-box">
                    <div class="kpi-box__top">
                        <span class="kpi-box__label">
                            Doanh thu hoa hồng sàn
                        </span>

                        <span class="kpi-box__symbol" aria-hidden="true">
                            %
                        </span>
                    </div>

                    <strong class="kpi-box__value">
                        {{ number_format((int) $platformRevenue, 0, ',', '.') }} ₫
                    </strong>

                    <p class="kpi-box__note">
                        Hoa hồng của các kiện hàng hợp lệ
                    </p>
                </article>

                <article class="kpi-box">
                    <div class="kpi-box__top">
                        <span class="kpi-box__label">
                            Tổng đơn hàng
                        </span>

                        <span class="kpi-box__symbol" aria-hidden="true">
                            #
                        </span>
                    </div>

                    <strong class="kpi-box__value">
                        {{ number_format((int) $orderCount, 0, ',', '.') }}
                    </strong>

                    <p class="kpi-box__note">
                        Tổng số đơn hàng cha đã tạo
                    </p>
                </article>

                <article class="kpi-box">
                    <div class="kpi-box__top">
                        <span class="kpi-box__label">
                            Kiện hàng hợp lệ
                        </span>

                        <span class="kpi-box__symbol" aria-hidden="true">
                            ✓
                        </span>
                    </div>

                    <strong class="kpi-box__value">
                        {{ number_format((int) $activeVendorOrdersCount, 0, ',', '.') }}
                    </strong>

                    <p class="kpi-box__note">
                        VendorOrder chưa bị hủy hoặc từ chối
                    </p>
                </article>
            </div>
        </section>

        {{-- 3. Giám sát vận hành --}}
        <section class="admin-section"
                 aria-labelledby="admin-operations-heading">
            <div class="admin-section__heading">
                <div>
                    <h2 id="admin-operations-heading">
                        Theo dõi vận hành
                    </h2>

                    <p>
                        Các vấn đề cần chú ý và quy mô hoạt động hiện tại.
                    </p>
                </div>
            </div>

            <div class="admin-operations">
                <div class="admin-panel">
                    <h3>Công việc cần theo dõi</h3>

                    <p class="admin-panel__description">
                        Chọn một mục để chuyển đến trang quản lý tương ứng.
                    </p>

                    <div class="admin-task-list">
                        <a class="admin-task"
                           href="{{ route('admin.vendors.index') }}">
                            <span>
                                <span class="admin-task__title">
                                    Vendor chờ duyệt
                                </span>

                                <span class="admin-task__description">
                                    Hồ sơ gian hàng đang chờ xét duyệt
                                </span>
                            </span>

                            <span class="admin-task__number">
                                {{ number_format((int) $pendingVendors, 0, ',', '.') }}
                            </span>
                        </a>

                        <a class="admin-task"
                           href="{{ route('admin.products.index') }}">
                            <span>
                                <span class="admin-task__title">
                                    Sản phẩm chờ duyệt
                                </span>

                                <span class="admin-task__description">
                                    Sản phẩm cần Admin kiểm tra
                                </span>
                            </span>

                            <span class="admin-task__number">
                                {{ number_format((int) $pendingProductsCount, 0, ',', '.') }}
                            </span>
                        </a>

                        <a class="admin-task"
                           href="{{ route('admin.users.index') }}">
                            <span>
                                <span class="admin-task__title">
                                    Tài khoản bị khóa
                                </span>

                                <span class="admin-task__description">
                                    Tài khoản cần theo dõi trạng thái
                                </span>
                            </span>

                            <span class="admin-task__number">
                                {{ number_format((int) $lockedUsersCount, 0, ',', '.') }}
                            </span>
                        </a>
                    </div>
                </div>

                <div class="admin-panel">
                    <h3>Quy mô hệ thống</h3>

                    <p class="admin-panel__description">
                        Số lượng tài khoản và gian hàng trên nền tảng.
                    </p>

                    <div class="admin-scale">
                        <div class="admin-scale__item">
                            <span class="admin-scale__label">
                                Tổng người dùng
                            </span>

                            <strong class="admin-scale__value">
                                {{ number_format((int) $userCount, 0, ',', '.') }}
                            </strong>
                        </div>

                        <div class="admin-scale__item">
                            <span class="admin-scale__label">
                                Tổng gian hàng
                            </span>

                            <strong class="admin-scale__value">
                                {{ number_format((int) $vendorCount, 0, ',', '.') }}
                            </strong>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- 4. Top 10 Vendor --}}
        <section class="admin-section"
                 aria-labelledby="admin-vendors-heading">
            <div class="admin-panel">
                <div class="admin-section__heading">
                    <div>
                        <h2 id="admin-vendors-heading">
                            Top 10 gian hàng theo GMV
                        </h2>

                        <p>
                            So sánh hiệu suất kinh doanh, số kiện hàng
                            và hoa hồng của các gian hàng.
                        </p>
                    </div>

                    <a class="admin-link"
                       href="{{ route('admin.vendors.index') }}">
                        Xem gian hàng
                    </a>
                </div>

                @php
                    $maxVendorGmv = max(
                        1,
                        (int) ($vendorKpis->max('active_gmv') ?? 0)
                    );

                    $vendorStatusLabels = [
                        'approved' => 'Đã duyệt',
                        'pending' => 'Chờ duyệt',
                        'rejected' => 'Từ chối',
                    ];
                @endphp

                <div class="admin-table-wrap"
                     role="region"
                     aria-label="Bảng xếp hạng gian hàng"
                     tabindex="0">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th scope="col">Gian hàng</th>
                                <th scope="col">Trạng thái</th>
                                <th scope="col">Tổng kiện</th>
                                <th scope="col">Kiện hợp lệ</th>
                                <th scope="col">GMV</th>
                                <th scope="col">Hoa hồng sàn</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($vendorKpis as $vendorKpi)
                                @php
                                    $vendorGmv = (int) ($vendorKpi->active_gmv ?? 0);

                                    $gmvPercentage = round(
                                        min(
                                            100,
                                            max(0, $vendorGmv / $maxVendorGmv * 100)
                                        ),
                                        1
                                    );

                                    $vendorStatus = (string) $vendorKpi->status;

                                    $statusClass = match ($vendorStatus) {
                                        'approved' => 'admin-status--approved',
                                        'pending' => 'admin-status--pending',
                                        'rejected' => 'admin-status--rejected',
                                        default => '',
                                    };
                                @endphp

                                <tr>
                                    <td>
                                        <div class="admin-vendor-name">
                                            <span class="admin-rank"
                                                  aria-hidden="true">
                                                {{ $loop->iteration }}
                                            </span>

                                            <a href="{{ route('admin.vendors.show', $vendorKpi) }}">
                                                {{ $vendorKpi->store_name }}
                                            </a>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="admin-status {{ $statusClass }}">
                                            {{ $vendorStatusLabels[$vendorStatus] ?? strtoupper($vendorStatus) }}
                                        </span>
                                    </td>

                                    <td>
                                        {{ number_format((int) $vendorKpi->total_orders_count, 0, ',', '.') }}
                                    </td>

                                    <td>
                                        {{ number_format((int) $vendorKpi->active_orders_count, 0, ',', '.') }}
                                    </td>

                                    <td class="money">
                                        <strong>
                                            {{ number_format($vendorGmv, 0, ',', '.') }} ₫
                                        </strong>

                                        <div class="admin-gmv-track"
                                             aria-hidden="true">
                                            <div class="admin-gmv-fill"
                                                 style="width: {{ $gmvPercentage }}%">
                                            </div>
                                        </div>
                                    </td>

                                    <td class="money">
                                        {{ number_format((int) $vendorKpi->active_commission, 0, ',', '.') }} ₫
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="admin-empty">
                                        Chưa có dữ liệu gian hàng.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <p class="admin-table-note">
                    Thanh màu hồng biểu diễn tỷ lệ GMV tương đối
                    so với gian hàng có GMV cao nhất trong danh sách.
                    GMV không bao gồm kiện hàng đã hủy hoặc từ chối.
                </p>
            </div>
        </section>

        {{-- 5. Đơn hàng gần đây --}}
        <section class="admin-section"
                 aria-labelledby="admin-recent-heading">
            <div class="admin-panel">
                <div class="admin-section__heading">
                    <div>
                        <h2 id="admin-recent-heading">
                            Đơn hàng gần đây
                        </h2>

                        <p>
                            Tám kiện hàng mới nhất được ghi nhận trên hệ thống.
                        </p>
                    </div>

                    <a class="admin-link"
                       href="{{ route('admin.orders.index') }}">
                        Tất cả đơn hàng
                    </a>
                </div>

                <div class="admin-table-wrap"
                     role="region"
                     aria-label="Bảng đơn hàng gần đây"
                     tabindex="0">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th scope="col">Mã kiện</th>
                                <th scope="col">Gian hàng</th>
                                <th scope="col">Người nhận</th>
                                <th scope="col">Tổng tiền</th>
                                <th scope="col">Hoa hồng</th>
                                <th scope="col">Trạng thái</th>
                                <th scope="col">Chi tiết</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($recentVendorOrders as $vendorOrder)
                                <tr>
                                    <td>
                                        <code>{{ $vendorOrder->vo_code }}</code>
                                    </td>

                                    <td>
                                        {{ $vendorOrder->vendor?->store_name ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $vendorOrder->order?->receiver ?? '—' }}
                                    </td>

                                    <td class="money">
                                        {{ number_format((int) $vendorOrder->total, 0, ',', '.') }} ₫
                                    </td>

                                    <td class="money">
                                        {{ number_format((int) $vendorOrder->commission_amount, 0, ',', '.') }} ₫
                                    </td>

                                    <td>
                                        <x-order-status :status="$vendorOrder->status" />
                                    </td>

                                    <td>
                                        <a class="admin-link"
                                           href="{{ route('admin.orders.show', $vendorOrder) }}">
                                            Xem
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="admin-empty">
                                        Chưa có đơn hàng nào trên hệ thống.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
@endsection
