<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidPaymentSignatureException;
use App\Exceptions\PaymentException;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentCallbackController extends Controller
{
    /**
     * Handle signed payment gateway callback idempotently by txn_ref.
     */
    public function __invoke(Request $request, PaymentService $paymentService): JsonResponse
    {
        $raw = $request->only(['txn_ref', 'status', 'amount', 'timestamp', 'signature']);

        try {
            $paymentService->verifySignature($raw);

            $validator = Validator::make($raw, [
                'txn_ref' => ['required', 'string', 'max:100'],
                'status' => ['required', 'string', 'max:30'],
                'amount' => ['required', 'integer', 'min:0'],
                'timestamp' => ['required', 'integer', 'min:1'],
                'signature' => ['required', 'string', 'max:256'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors()->first(),
                    'error' => 'validation_error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $result = $paymentService->handleCallback($validator->validated(), null, $request->ip());
        } catch (InvalidPaymentSignatureException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'invalid_signature',
            ], 403);
        } catch (PaymentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'payment_error',
            ], 422);
        }

        $payment = $result['payment'];

        return response()->json([
            'message' => $result['already_processed']
                ? 'Giao dịch đã được xử lý trước đó (idempotent).'
                : 'Cập nhật trạng thái thanh toán thành công.',
            'already_processed' => $result['already_processed'],
            'payment' => [
                'id' => $payment->id,
                'order_id' => $payment->order_id,
                'method' => $payment->method,
                'amount' => (int) $payment->amount,
                'status' => $payment->status,
                'txn_ref' => $payment->txn_ref,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'order_payment_status' => $payment->order?->payment_status,
            ],
        ], 200);
    }
}
