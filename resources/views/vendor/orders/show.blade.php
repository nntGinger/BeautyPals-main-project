@extends('layouts.app')

@section('title', 'Đơn hàng '.$vendorOrder->vo_code)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/vendor-operations.css') }}">
@endpush

@section('content')
    <a href="{{ route('vendor.orders.index') }}">← Danh sách đơn hàng</a>
    <div class="operations-heading">
        <div><p class="eyebrow">Chi tiết đơn cửa hàng</p><h1>{{ $vendorOrder->vo_code }}</h1><p class="muted">Đơn gốc: {{ $vendorOrder->order->order_code }} · {{ $vendorOrder->order->placed_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}</p></div>
        <x-order-status :status="$vendorOrder->status" />
    </div>
    <div class="operations-columns">
        <section class="operations-panel"><h2>Thông tin giao hàng</h2><p><strong>{{ $vendorOrder->order->receiver }}</strong> · {{ $vendorOrder->order->phone }}</p><p>{{ $vendorOrder->order->address_text }}</p><p class="muted">Thanh toán: {{ strtoupper($vendorOrder->order->payment_method) }} · {{ $vendorOrder->order->payment_status === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán' }}</p>@if ($vendorOrder->tracking_code)<p>Vận đơn: {{ $vendorOrder->tracking_code }}</p>@endif</section>
        <section class="operations-panel"><h2>Tổng thanh toán</h2><dl class="operations-totals"><dt>Tiền hàng</dt><dd>{{ number_format($vendorOrder->subtotal, 0, ',', '.') }} đ</dd><dt>Phí giao hàng</dt><dd>{{ number_format($vendorOrder->shipping_fee, 0, ',', '.') }} đ</dd><dt>Giảm giá</dt><dd>{{ number_format($vendorOrder->discount, 0, ',', '.') }} đ</dd><dt><strong>Tổng cộng</strong></dt><dd><strong>{{ number_format($vendorOrder->total, 0, ',', '.') }} đ</strong></dd></dl></section>
    </div>
    <section class="operations-panel">
        <h2>Sản phẩm & phân bổ lô FEFO</h2>
        <div class="operations-table-wrap"><table class="operations-table"><thead><tr><th>Sản phẩm / SKU</th><th>Số lượng</th><th>Đơn giá</th><th>Thành tiền</th><th>Phân bổ lô</th></tr></thead><tbody>
            @forelse ($vendorOrder->items as $item)
                <tr><td><strong>{{ $item->product_name }}</strong><small>{{ $item->sku_code }}</small></td><td>{{ $item->qty }}</td><td class="money">{{ number_format($item->unit_price, 0, ',', '.') }} đ</td><td class="money">{{ number_format($item->line_total, 0, ',', '.') }} đ</td><td>
                    @forelse ($item->allocations as $allocation)
                        <div class="allocation"><a href="{{ route('vendor.inventory.show', $allocation->batch) }}">{{ $allocation->batch->batch_no }}</a><small>HSD {{ $allocation->batch->exp_date->format('d/m/Y') }} · {{ $allocation->qty }} đơn vị</small><small>{{ match ($allocation->status) { 'held' => 'Đang giữ chỗ', 'committed' => 'Đã xuất kho', 'released' => 'Đã nhả giữ chỗ', default => $allocation->status } }}</small></div>
                    @empty<p class="muted">Chưa có phân bổ lô.</p>@endforelse
                </td></tr>
            @empty<tr><td colspan="5">Đơn chưa có sản phẩm.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    <section class="operations-panel">
        <h2>Xử lý đơn hàng</h2>
        @error('status')<p class="error" role="alert">{{ $message }}</p>@enderror
        @error('reason')<p class="error" role="alert">{{ $message }}</p>@enderror
        @can('updateStatus', $vendorOrder)
            @forelse ($transitions as $target)
                <form method="POST" action="{{ route('vendor.orders.transition', $vendorOrder) }}" class="transition-form">
                    @csrf
                    <input type="hidden" name="status" value="{{ $target->value }}">
                    @if (in_array($target->value, ['rejected', 'cancelled']))
                        <label for="reason-{{ $target->value }}">Lý do {{ $target->value === 'rejected' ? 'từ chối (bắt buộc)' : 'hủy (không bắt buộc)' }}</label>
                        <textarea id="reason-{{ $target->value }}" name="reason" maxlength="255" rows="2" @required($target->value === 'rejected')>{{ old('status') === $target->value ? old('reason') : '' }}</textarea>
                    @endif
                    <button type="submit" class="{{ in_array($target->value, ['rejected', 'cancelled']) ? 'btn-ghost' : '' }}">{{ match ($target->value) { 'confirmed' => 'Xác nhận đơn', 'packed' => 'Đã đóng gói', 'shipped' => 'Bàn giao vận chuyển', 'delivered' => 'Xác nhận đã giao', 'completed' => 'Hoàn tất đơn', 'cancelled' => 'Hủy đơn', 'rejected' => 'Từ chối đơn' } }}</button>
                </form>
            @empty<p class="muted">Đơn đã kết thúc, không còn trạng thái kế tiếp.</p>@endforelse
        @endcan
    </section>
    <section class="operations-panel">
        <h2>Lịch sử trạng thái</h2>
        <ol class="order-timeline">
            @forelse ($vendorOrder->statusHistory as $entry)
                <li><strong>{{ $entry->from_status ? (\App\Enums\OrderStatus::tryFrom($entry->from_status)?->label() ?? $entry->from_status).' → ' : '' }}{{ \App\Enums\OrderStatus::tryFrom($entry->to_status)?->label() ?? $entry->to_status }}</strong><small>{{ $entry->created_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s') }} · {{ $entry->changer?->name ?? 'Hệ thống' }}</small>@if ($entry->note)<p>{{ $entry->note }}</p>@endif</li>
            @empty<li class="muted">Chưa có lịch sử chuyển trạng thái.</li>@endforelse
        </ol>
    </section>
@endsection
