<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Exceptions\InvalidPaymentSignatureException;
use App\Exceptions\PaymentException;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public const METHOD_COD = 'cod';

    public const GATEWAY_METHODS = ['mock_gateway', 'vnpay', 'momo'];

    public const SUPPORTED_METHODS = [self::METHOD_COD, ...self::GATEWAY_METHODS];

    /**
     * Initiate a payment session for an order without altering existing COD semantics.
     */
    public function initiate(
        Order $order,
        string $method = 'mock_gateway',
        ?int $actorUserId = null,
        ?string $ip = null,
    ): Payment {
        $method = strtolower(trim($method));

        if (! in_array($method, self::SUPPORTED_METHODS, true)) {
            throw new PaymentException("Phương thức thanh toán [{$method}] không được hỗ trợ.");
        }

        return DB::transaction(function () use ($order, $method, $actorUserId, $ip): Payment {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->status === 'cancelled') {
                throw new PaymentException('Không thể khởi tạo thanh toán cho đơn hàng đã hủy.');
            }

            if ($lockedOrder->payment_status === PaymentStatus::PAID->value) {
                throw new PaymentException('Đơn hàng này đã được thanh toán hoàn tất.');
            }

            if ((int) $lockedOrder->grand_total <= 0) {
                throw new PaymentException('Số tiền thanh toán phải lớn hơn 0 đồng.');
            }

            if ($method === self::METHOD_COD) {
                return $this->initiateCod($lockedOrder);
            }

            // Preserve COD orders once they have progressed beyond initial pending state.
            if ($lockedOrder->payment_method === self::METHOD_COD && $lockedOrder->status !== 'pending') {
                throw new PaymentException('Đơn hàng COD đang xử lý không thể chuyển sang thanh toán trực tuyến.');
            }

            $existingPending = Payment::query()
                ->where('order_id', $lockedOrder->id)
                ->where('method', $method)
                ->where('status', PaymentStatus::PENDING->value)
                ->where('amount', (int) $lockedOrder->grand_total)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            if ($existingPending !== null) {
                if ($lockedOrder->payment_method !== $method) {
                    $lockedOrder->update(['payment_method' => $method]);
                }

                return $existingPending;
            }

            $txnRef = $this->generateUniqueTxnRef($method);

            $payment = Payment::query()->create([
                'order_id' => $lockedOrder->id,
                'method' => $method,
                'amount' => (int) $lockedOrder->grand_total,
                'status' => PaymentStatus::PENDING->value,
                'txn_ref' => $txnRef,
                'paid_at' => null,
            ]);

            $oldMethod = $lockedOrder->payment_method;
            $oldPaymentStatus = $lockedOrder->payment_status;

            $lockedOrder->update([
                'payment_method' => $method,
                'payment_status' => PaymentStatus::PENDING->value,
            ]);

            AuditLog::query()->create([
                'actor_id' => $actorUserId,
                'action' => 'payment_initiated',
                'entity_type' => 'payment',
                'entity_id' => $payment->id,
                'old_values' => [
                    'payment_method' => $oldMethod,
                    'payment_status' => $oldPaymentStatus,
                ],
                'new_values' => [
                    'order_id' => $lockedOrder->id,
                    'method' => $payment->method,
                    'amount' => (int) $payment->amount,
                    'status' => $payment->status,
                    'txn_ref' => $payment->txn_ref,
                ],
                'ip' => $ip,
            ]);

            return $payment;
        }, 3);
    }

    /**
     * Build a cryptographically signed callback payload using HMAC-SHA256.
     *
     * @return array{txn_ref: string, amount: int, status: string, timestamp: int, signature: string}
     */
    public function signPayload(string $txnRef, int $amount, string $status, ?int $timestamp = null): array
    {
        $ts = $timestamp ?? now()->timestamp;
        $canonical = $this->canonicalData($txnRef, $amount, $status, $ts);

        return [
            ...$canonical,
            'signature' => $this->computeSignature($canonical),
        ];
    }

    /**
     * Verify HMAC-SHA256 signature and timestamp freshness for incoming callback payloads.
     */
    public function verifySignature(array $payload, ?int $nowTimestamp = null): void
    {
        $signature = isset($payload['signature']) && is_string($payload['signature'])
            ? trim($payload['signature'])
            : '';
        if ($signature === '') {
            throw new InvalidPaymentSignatureException('Thiếu chữ ký xác thực callback thanh toán.');
        }

        $txnRef = isset($payload['txn_ref']) && is_scalar($payload['txn_ref'])
            ? trim((string) $payload['txn_ref'])
            : '';
        $status = isset($payload['status']) && is_scalar($payload['status'])
            ? trim((string) $payload['status'])
            : '';
        $amountRaw = $payload['amount'] ?? null;
        $timestampRaw = $payload['timestamp'] ?? null;

        $amountValid = is_int($amountRaw) || (is_string($amountRaw) && preg_match('/^\d+$/', trim($amountRaw)) === 1);
        $timestampValid = is_int($timestampRaw) || (is_string($timestampRaw) && preg_match('/^\d+$/', trim($timestampRaw)) === 1);

        if ($txnRef === '' || $status === '' || ! $amountValid || ! $timestampValid) {
            throw new InvalidPaymentSignatureException('Dữ liệu chữ ký callback thanh toán không hợp lệ.');
        }

        $amount = (int) $amountRaw;
        $timestamp = (int) $timestampRaw;
        if ($amount < 0 || $timestamp <= 0) {
            throw new InvalidPaymentSignatureException('Dữ liệu chữ ký callback thanh toán không hợp lệ.');
        }

        $expected = $this->computeSignature($this->canonicalData($txnRef, $amount, $status, $timestamp));

        if (! hash_equals($expected, strtolower($signature))) {
            throw new InvalidPaymentSignatureException('Chữ ký callback thanh toán không hợp lệ.');
        }

        $now = $nowTimestamp ?? now()->timestamp;
        $ttl = (int) config('services.payment_gateway.signature_ttl_seconds', 900);

        if ($ttl > 0 && abs($now - $timestamp) > $ttl) {
            throw new InvalidPaymentSignatureException('Chữ ký callback thanh toán đã hết hạn.');
        }
    }

    /**
     * Pre-sign pending/paid/failed callback payloads for interactive mock gateway simulation.
     *
     * @return array<string, array{txn_ref: string, amount: int, status: string, timestamp: int, signature: string}>
     */
    public function createSimulationPayloads(Payment $payment, ?int $timestamp = null): array
    {
        $ts = $timestamp ?? now()->timestamp;
        $txnRef = (string) $payment->txn_ref;
        $amount = (int) $payment->amount;

        return [
            'pending' => $this->signPayload($txnRef, $amount, 'pending', $ts),
            'paid' => $this->signPayload($txnRef, $amount, 'paid', $ts),
            'failed' => $this->signPayload($txnRef, $amount, 'failed', $ts),
        ];
    }

    /**
     * Process an external/simulated payment gateway callback idempotently by txn_ref.
     *
     * @return array{payment: Payment, already_processed: bool}
     */
    public function handleCallback(array $payload, ?int $actorUserId = null, ?string $ip = null): array
    {
        $this->verifySignature($payload);

        $txnRef = trim((string) $payload['txn_ref']);
        $targetStatus = PaymentStatus::fromGatewayCallback((string) $payload['status']);
        $callbackAmount = (int) $payload['amount'];

        $lookup = Payment::query()->where('txn_ref', $txnRef)->first();
        if ($lookup === null) {
            throw new PaymentException("Không tìm thấy giao dịch với mã [{$txnRef}].");
        }

        if ($lookup->method === self::METHOD_COD) {
            throw new PaymentException('Không thể xử lý callback cổng thanh toán cho giao dịch COD.');
        }

        return DB::transaction(function () use ($lookup, $targetStatus, $callbackAmount, $actorUserId, $ip): array {
            // Keep consistent Order -> Payment lock ordering to prevent deadlocks across order workflows.
            $order = Order::query()->lockForUpdate()->findOrFail($lookup->order_id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($lookup->id);

            if ($payment->method === self::METHOD_COD) {
                throw new PaymentException('Không thể xử lý callback cổng thanh toán cho giao dịch COD.');
            }

            if ((int) $payment->amount !== $callbackAmount) {
                throw new PaymentException('Số tiền callback không khớp với giá trị giao dịch đã khởi tạo.');
            }

            if ($payment->status === $targetStatus->value) {
                return [
                    'payment' => $payment->load('order'),
                    'already_processed' => true,
                ];
            }

            if ($payment->status === PaymentStatus::PAID->value) {
                throw new PaymentException('Giao dịch đã thanh toán thành công, không thể chuyển sang trạng thái khác.');
            }

            if ($payment->status === PaymentStatus::FAILED->value) {
                throw new PaymentException('Giao dịch đã thất bại trước đó, vui lòng khởi tạo giao dịch mới.');
            }

            if ($targetStatus === PaymentStatus::PAID && $order->status === 'cancelled') {
                throw new PaymentException('Không thể xác nhận thanh toán cho đơn hàng đã bị hủy.');
            }

            $oldValues = [
                'payment_status' => $payment->status,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'order_payment_status' => $order->payment_status,
            ];

            if ($targetStatus === PaymentStatus::PAID) {
                $payment->update([
                    'status' => PaymentStatus::PAID->value,
                    'paid_at' => now(),
                ]);

                $order->update([
                    'payment_method' => $payment->method,
                    'payment_status' => PaymentStatus::PAID->value,
                ]);
            } elseif ($targetStatus === PaymentStatus::FAILED) {
                $payment->update([
                    'status' => PaymentStatus::FAILED->value,
                    'paid_at' => null,
                ]);

                if ($order->payment_status !== PaymentStatus::PAID->value) {
                    $order->update([
                        'payment_status' => PaymentStatus::FAILED->value,
                    ]);
                }
            }

            $freshPayment = $payment->fresh('order');

            AuditLog::query()->create([
                'actor_id' => $actorUserId,
                'action' => 'payment_callback_'.$targetStatus->value,
                'entity_type' => 'payment',
                'entity_id' => $payment->id,
                'old_values' => $oldValues,
                'new_values' => [
                    'payment_status' => $freshPayment->status,
                    'paid_at' => $freshPayment->paid_at?->toIso8601String(),
                    'order_payment_status' => $freshPayment->order->payment_status,
                    'txn_ref' => $freshPayment->txn_ref,
                ],
                'ip' => $ip,
            ]);

            return [
                'payment' => $freshPayment,
                'already_processed' => false,
            ];
        }, 3);
    }

    private function initiateCod(Order $lockedOrder): Payment
    {
        $existingCod = Payment::query()
            ->where('order_id', $lockedOrder->id)
            ->where('method', self::METHOD_COD)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        if ($existingCod !== null) {
            return $existingCod;
        }

        $payment = Payment::query()->create([
            'order_id' => $lockedOrder->id,
            'method' => self::METHOD_COD,
            'amount' => (int) $lockedOrder->grand_total,
            'status' => PaymentStatus::PENDING->value,
            'txn_ref' => $this->generateUniqueCodTxnRef($lockedOrder->order_code),
            'paid_at' => null,
        ]);

        $lockedOrder->update([
            'payment_method' => self::METHOD_COD,
            'payment_status' => PaymentStatus::PENDING->value,
        ]);

        return $payment;
    }

    private function generateUniqueTxnRef(string $method): string
    {
        $prefix = match ($method) {
            'vnpay' => 'VNPAY',
            'momo' => 'MOMO',
            default => 'PAY',
        };

        do {
            $candidate = $prefix.'-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
        } while (Payment::query()->where('txn_ref', $candidate)->exists());

        return $candidate;
    }

    private function generateUniqueCodTxnRef(string $orderCode): string
    {
        $base = 'COD-'.$orderCode;
        if (! Payment::query()->where('txn_ref', $base)->exists()) {
            return $base;
        }

        do {
            $candidate = $base.'-'.strtoupper(Str::random(4));
        } while (Payment::query()->where('txn_ref', $candidate)->exists());

        return $candidate;
    }

    /**
     * @return array{amount: int, status: string, timestamp: int, txn_ref: string}
     */
    private function canonicalData(string $txnRef, int $amount, string $status, int $timestamp): array
    {
        $data = [
            'amount' => $amount,
            'status' => trim($status),
            'timestamp' => $timestamp,
            'txn_ref' => trim($txnRef),
        ];

        ksort($data);

        return $data;
    }

    private function computeSignature(array $canonicalData): string
    {
        $secret = (string) config('services.payment_gateway.secret', '');
        if ($secret === '') {
            throw new InvalidPaymentSignatureException('Chưa cấu hình khóa bí mật cho cổng thanh toán.');
        }

        return hash_hmac('sha256', http_build_query($canonicalData), $secret);
    }
}
