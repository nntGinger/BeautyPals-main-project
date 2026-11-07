@extends('layouts.app')

@section('title', 'Quản lý cửa hàng – Admin')

@section('content')
<h1>Quản lý cửa hàng Vendor</h1>

{{-- Status filter tabs --}}
<div style="display:flex;gap:.5rem;margin-bottom:1.2rem;flex-wrap:wrap;">
    @foreach(['','pending','approved','rejected','suspended'] as $tab)
        <a href="{{ route('admin.vendors.index', $tab ? ['status' => $tab] : []) }}"
           style="padding:.4rem .9rem;border-radius:999px;border:1px solid var(--line);
                  background:{{ $status === ($tab ?: null) ? 'var(--accent)' : 'var(--card)' }};
                  color:{{ $status === ($tab ?: null) ? '#fff' : 'inherit' }};">
            {{ $tab ? ucfirst($tab) : 'Tất cả' }}
        </a>
    @endforeach
</div>

@include('partials.flash')

<div class="card" style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="border-bottom:2px solid var(--line);">
                <th style="text-align:left;padding:.5rem .75rem;">#</th>
                <th style="text-align:left;padding:.5rem .75rem;">Cửa hàng</th>
                <th style="text-align:left;padding:.5rem .75rem;">Chủ sở hữu</th>
                <th style="text-align:left;padding:.5rem .75rem;">MST</th>
                <th style="text-align:left;padding:.5rem .75rem;">Trạng thái</th>
                <th style="text-align:left;padding:.5rem .75rem;">Đăng ký</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @forelse($vendors as $vendor)
            <tr style="border-bottom:1px solid var(--line);">
                <td style="padding:.5rem .75rem;" class="muted">{{ $vendor->id }}</td>
                <td style="padding:.5rem .75rem;font-weight:600;">{{ $vendor->store_name }}</td>
                <td style="padding:.5rem .75rem;">{{ $vendor->owner->name }}</td>
                <td style="padding:.5rem .75rem;" class="muted">{{ $vendor->tax_code }}</td>
                <td style="padding:.5rem .75rem;">
                    @php
                        $colors = ['pending'=>'#b78a00','approved'=>'var(--ok)','rejected'=>'var(--danger)','suspended'=>'#555'];
                    @endphp
                    <span style="font-weight:600;color:{{ $colors[$vendor->status] ?? 'inherit' }};">
                        {{ ucfirst($vendor->status) }}
                    </span>
                </td>
                <td style="padding:.5rem .75rem;" class="muted">{{ $vendor->created_at->format('d/m/Y') }}</td>
                <td style="padding:.5rem .75rem;">
                    <a href="{{ route('admin.vendors.show', $vendor) }}">Xem chi tiết →</a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" style="padding:1.5rem;text-align:center;" class="muted">Không có cửa hàng nào.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:1rem;">
    {{ $vendors->links() }}
</div>
@endsection

