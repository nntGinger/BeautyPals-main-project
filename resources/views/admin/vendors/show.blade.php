@extends('layouts.app')

@section('title', $vendor->store_name . ' – Admin')

@section('content')
<p><a href="{{ route('admin.vendors.index') }}">← Danh sách cửa hàng</a></p>

<h1>{{ $vendor->store_name }}</h1>

@include('partials.flash')

@php
    $statusColor = ['pending'=>'#b78a00','approved'=>'var(--ok)','rejected'=>'var(--danger)','suspended'=>'#555'];
@endphp

<div class="card" style="margin-bottom:1.2rem;">
    <table style="width:100%;border-collapse:collapse;">
        <tr><th style="text-align:left;padding:.4rem .6rem;width:160px;">Trạng thái</th>
            <td style="padding:.4rem .6rem;font-weight:700;color:{{ $statusColor[$vendor->status] ?? 'inherit' }};">
                {{ ucfirst($vendor->status) }}</td></tr>
        <tr><th style="text-align:left;padding:.4rem .6rem;">Chủ sở hữu</th>
            <td style="padding:.4rem .6rem;">{{ $vendor->owner->name }} ({{ $vendor->owner->email }})</td></tr>
        <tr><th style="text-align:left;padding:.4rem .6rem;">Mã số thuế</th>
            <td style="padding:.4rem .6rem;">{{ $vendor->tax_code }}</td></tr>
        <tr><th style="text-align:left;padding:.4rem .6rem;">Địa chỉ</th>
            <td style="padding:.4rem .6rem;">{{ $vendor->address ?? '—' }}</td></tr>
        <tr><th style="text-align:left;padding:.4rem .6rem;">Giấy phép</th>
            <td style="padding:.4rem .6rem;">
                @if($vendor->license_file)
                    <a href="{{ route('admin.vendors.license', $vendor) }}">Tải về giấy phép</a>
                @else
                    <span class="muted">Chưa có</span>
                @endif
            </td></tr>
        @if($vendor->reject_reason)
        <tr><th style="text-align:left;padding:.4rem .6rem;">Lý do từ chối</th>
            <td style="padding:.4rem .6rem;color:var(--danger);">{{ $vendor->reject_reason }}</td></tr>
        @endif
        @if($vendor->suspended_reason)
        <tr><th style="text-align:left;padding:.4rem .6rem;">Lý do khóa</th>
            <td style="padding:.4rem .6rem;color:var(--danger);">{{ $vendor->suspended_reason }}</td></tr>
        @endif
        @if($vendor->approver)
        <tr><th style="text-align:left;padding:.4rem .6rem;">Duyệt bởi</th>
            <td style="padding:.4rem .6rem;">{{ $vendor->approver->name }}
                ({{ $vendor->approved_at?->format('d/m/Y H:i') }})</td></tr>
        @endif
    </table>
</div>

{{-- Action buttons based on current status --}}
@php
    $transitions = [
        'pending'   => ['approve' => ['label'=>'✅ Duyệt',   'color'=>'var(--ok)',     'needsReason'=>false],
                        'reject'  => ['label'=>'❌ Từ chối', 'color'=>'var(--danger)', 'needsReason'=>true]],
        'approved'  => ['suspend' => ['label'=>'🔒 Khóa',    'color'=>'#555',          'needsReason'=>true]],
        'suspended' => ['unsuspend'=>['label'=>'🔓 Mở khóa', 'color'=>'var(--ok)',     'needsReason'=>false]],
        'rejected'  => ['approve' => ['label'=>'✅ Duyệt',   'color'=>'var(--ok)',     'needsReason'=>false]],
    ];
    $available = $transitions[$vendor->status] ?? [];
@endphp

@foreach($available as $action => $cfg)
<div class="card" style="margin-bottom:.8rem;">
    <form method="POST" action="{{ route('admin.vendors.status', $vendor) }}">
        @csrf
        <input type="hidden" name="action" value="{{ $action }}">
        <div style="display:flex;align-items:flex-end;gap:.8rem;flex-wrap:wrap;">
            @if($cfg['needsReason'])
            <div style="flex:1;min-width:200px;">
                <label for="reason_{{ $action }}" style="margin-top:0;">
                    Lý do <span style="color:var(--danger);">*</span>
                </label>
                <input id="reason_{{ $action }}" type="text" name="reason"
                       placeholder="Nhập lý do..." required>
                @error('reason') <p class="error">{{ $message }}</p> @enderror
            </div>
            @endif
            <button type="submit" style="background:{{ $cfg['color'] }};white-space:nowrap;">
                {{ $cfg['label'] }}
            </button>
        </div>
    </form>
</div>
@endforeach

@endsection

