@props(['status'])
@php
    $labels = ['draft' => 'Nháp', 'pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Bị từ chối', 'hidden' => 'Đã ẩn'];
    $variant = array_key_exists($status, $labels) ? $status : 'unknown';
@endphp
@once
    @push('styles')
        <style>
            .product-status { display: inline-flex; align-items: center; gap: .4rem; align-self: flex-start; padding: .3rem .65rem; border-radius: 6px; font-size: .75rem; font-weight: 600; line-height: 1.4; white-space: nowrap; background: #edf0f3; color: #526071; }
            .product-status::before { content: ''; width: 6px; height: 6px; flex-shrink: 0; border-radius: 50%; background: currentColor; }
            .product-status--draft { background: #edf0f3; color: #526071; }
            .product-status--pending { background: #fff3d6; color: #8b6008; }
            .product-status--approved { background: #e5f4eb; color: #236542; }
            .product-status--rejected { background: #fde8e8; color: #a62c37; }
            .product-status--hidden { background: #eee8fa; color: #6b4699; }
        </style>
    @endpush
@endonce
<span class="product-status product-status--{{ $variant }}">{{ $labels[$status] ?? $status }}</span>
