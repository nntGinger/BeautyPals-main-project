@extends('layouts.app')

@section('title', 'Cổng thanh toán giả lập - ' . $payment->txn_ref)

@section('content')
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
            <div>
                <h1>Cổng thanh toán giả lập ({{ strtoupper($payment->method) }})</h1>
                <p class="muted" style="margin:0;">Đơn hàng <strong>{{ $order->order_code }}</strong> · Mã giao dịch <code>{{ $payment->txn_ref }}</code></p>
            </div>
            <a class="btn-ghost btn" href="{{ route('customer.payments.simulator') }}">← Danh sách giao dịch</a>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin:1.25rem 0;padding:1rem;background:var(--bg);border-radius:12px;border:1px solid var(--line);">
            <div>
                <div class="muted" style="font-size:0.85rem;">Số tiền thanh toán</div>
                <div style="font-size:1.2rem;font-weight:700;">{{ number_format($payment->amount, 0, ',', '.') }} đ</div>
            </div>
            <div>
                <div class="muted" style="font-size:0.85rem;">Trạng thái Payment</div>
                <div style="font-size:1.1rem;font-weight:700;color:{{ $payment->status === 'paid' ? 'var(--ok)' : ($payment->status === 'failed' ? 'var(--danger)' : 'var(--accent-dark)') }};">
                    {{ strtoupper($payment->status) }}
                </div>
            </div>
            <div>
                <div class="muted" style="font-size:0.85rem;">TT Thanh toán của Order</div>
                <div style="font-size:1.1rem;font-weight:700;">{{ strtoupper($order->payment_status) }}</div>
            </div>
            <div>
                <div class="muted" style="font-size:0.85rem;">Thời điểm thanh toán</div>
                <div>{{ $payment->paid_at ? $payment->paid_at->format('d/m/Y H:i:s') : 'Chưa thanh toán' }}</div>
            </div>
        </div>

        <h2 style="font-size:1.15rem;margin-top:1.25rem;">Gửi Callback có chữ ký HMAC-SHA256</h2>
        <p class="muted" style="margin-top:0.25rem;">
            Bạn có thể bấm lặp lại cùng một nút (ví dụ bấm <strong>Thành công (PAID)</strong> hai lần liên tiếp) để kiểm chứng tính <strong>Idempotent theo <code>txn_ref</code></strong>, hoặc thử nút chữ ký giả mạo để kiểm chứng từ chối 403.
        </p>

        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-top:1rem;">
            <form method="POST" action="{{ route('customer.payments.simulate', $payment) }}">
                @csrf
                <input type="hidden" name="outcome" value="pending">
                <button type="submit" class="btn-ghost">Giả lập Chờ (PENDING)</button>
            </form>

            <form method="POST" action="{{ route('customer.payments.simulate', $payment) }}">
                @csrf
                <input type="hidden" name="outcome" value="paid">
                <button type="submit" style="background:var(--ok);">Giả lập Thành công (PAID)</button>
            </form>

            <form method="POST" action="{{ route('customer.payments.simulate', $payment) }}">
                @csrf
                <input type="hidden" name="outcome" value="failed">
                <button type="submit" style="background:var(--danger);">Giả lập Thất bại (FAILED)</button>
            </form>

            <form method="POST" action="{{ route('customer.payments.simulate', $payment) }}">
                @csrf
                <input type="hidden" name="outcome" value="paid">
                <input type="hidden" name="tamper" value="1">
                <button type="submit" class="btn-ghost" style="border-color:var(--danger);color:var(--danger);">Thử Chữ ký sai (403)</button>
            </form>
        </div>

        @if ($payment->status === 'failed')
            <div style="margin-top:1.25rem;padding:1rem;border-radius:12px;border:1px solid var(--line);background:#fff;">
                <p style="margin:0 0 0.6rem;">Giao dịch hiện tại đã thất bại. Để thử lại thanh toán, khởi tạo một <code>txn_ref</code> mới:</p>
                <form method="POST" action="{{ route('customer.payments.initiate', $order) }}">
                    @csrf
                    <input type="hidden" name="method" value="{{ $payment->method }}">
                    <button type="submit">Khởi tạo giao dịch mới</button>
                </form>
            </div>
        @endif

        <details style="margin-top:1.5rem;">
            <summary style="cursor:pointer;font-weight:600;">Xem Payload &amp; Chữ ký mẫu gửi tới POST {{ route('payments.callback', [], false) }}</summary>
            <pre style="margin-top:0.75rem;padding:1rem;background:#2b211c;color:#faf6f3;border-radius:10px;overflow-x:auto;font-size:0.85rem;">{{ json_encode($simulationPayloads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </details>
    </div>
@endsection

