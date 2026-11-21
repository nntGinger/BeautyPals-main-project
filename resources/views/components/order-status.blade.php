@props(['status'])
<span class="order-status order-status-{{ $status }}">{{ \App\Enums\OrderStatus::tryFrom($status)?->label() ?? $status }}</span>
