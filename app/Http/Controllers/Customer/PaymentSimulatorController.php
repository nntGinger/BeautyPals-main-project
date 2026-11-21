<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\InvalidPaymentSignatureException;
use App\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentSimulatorController extends Controller
{
    public function index(Request $request): View
    {
        $orders = $request->user()
            ->orders()
            ->with(['payments' => fn ($query) => $query->orderByDesc('id')])
            ->orderByDesc('id')
            ->paginate(10);

        return view('customer.payments.index', [
            'orders' => $orders,
            'gatewayMethods' => PaymentService::GATEWAY_METHODS,
        ]);
    }

    public function initiate(Request $request, Order $order, PaymentService $paymentService): RedirectResponse
    {
        $this->authorize('view', $order);

        $validated = $request->validate([
            'method' => ['required', 'string', Rule::in(PaymentService::GATEWAY_METHODS)],
        ]);

        try {
            $payment = $paymentService->initiate(
                $order,
                $validated['method'],
                $request->user()->id,
                $request->ip(),
            );
        } catch (PaymentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('customer.payments.gateway', $payment)
            ->with('success', "Đã khởi tạo phiên thanh toán [{$payment->txn_ref}].");
    }

    public function gateway(Payment $payment, PaymentService $paymentService): View|RedirectResponse
    {
        $payment->loadMissing('order');
        $this->authorize('view', $payment->order);

        if ($payment->method === PaymentService::METHOD_COD) {
            return to_route('customer.payments.simulator')
                ->with('error', 'Đơn hàng COD không sử dụng màn hình cổng thanh toán trực tuyến.');
        }

        return view('customer.payments.gateway', [
            'payment' => $payment,
            'order' => $payment->order,
            'simulationPayloads' => $paymentService->createSimulationPayloads($payment),
        ]);
    }

    public function simulate(Request $request, Payment $payment, PaymentService $paymentService): RedirectResponse
    {
        $payment->loadMissing('order');
        $this->authorize('view', $payment->order);

        $validated = $request->validate([
            'outcome' => ['required', 'string', Rule::in(['pending', 'paid', 'failed'])],
            'tamper' => ['nullable', 'boolean'],
        ]);

        $payload = $paymentService->signPayload(
            (string) $payment->txn_ref,
            (int) $payment->amount,
            $validated['outcome'],
        );

        if ($request->boolean('tamper')) {
            $payload['signature'] = str_repeat('0', 64);
        }

        try {
            $result = $paymentService->handleCallback($payload, $request->user()->id, $request->ip());
        } catch (InvalidPaymentSignatureException $e) {
            return back()->with('error', 'Từ chối callback (403): '.$e->getMessage());
        } catch (PaymentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = $result['already_processed']
            ? "Callback lặp lại với mã [{$payment->txn_ref}] được bỏ qua an toàn (Idempotent — không tạo giao dịch trùng)."
            : "Đã xử lý callback [{$validated['outcome']}] thành công cho mã [{$payment->txn_ref}].";

        return to_route('customer.payments.gateway', $payment)->with('success', $message);
    }
}
