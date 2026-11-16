<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Exceptions\InvalidPaymentSignatureException;
use App\Exceptions\PaymentException;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Database\Seeders\OrderDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentService = app(PaymentService::class);
    }

    private function createOrderForCustomer(User $customer, array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'order_code' => 'ORD-TEST-'.strtoupper(Str::random(6)),
            'customer_id' => $customer->id,
            'receiver' => $customer->name,
            'phone' => $customer->phone ?? '0900000001',
            'address_text' => '123 Nguyễn Huệ, Quận 1, TP.HCM',
            'subtotal' => 450000,
            'shipping_total' => 0,
            'discount_total' => 0,
            'grand_total' => 450000,
            'payment_method' => 'mock_gateway',
            'payment_status' => 'pending',
            'status' => 'pending',
            'idempotency_key' => (string) Str::uuid(),
            'placed_at' => now(),
        ], $overrides));
    }

    public function test_initiate_creates_pending_gateway_payment_with_audit_log_and_reuses_pending_session(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->createOrderForCustomer($customer, ['grand_total' => 520000]);

        $payment = $this->paymentService->initiate($order, 'vnpay', $customer->id, '127.0.0.1');

        $this->assertSame($order->id, $payment->order_id);
        $this->assertSame('vnpay', $payment->method);
        $this->assertSame(520000, $payment->amount);
        $this->assertSame('pending', $payment->status);
        $this->assertStringStartsWith('VNPAY-', (string) $payment->txn_ref);
        $this->assertNull($payment->paid_at);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $customer->id,
            'action' => 'payment_initiated',
            'entity_type' => 'payment',
            'entity_id' => $payment->id,
        ]);

        // Re-initiating same pending gateway method reuses the pending session without creating duplicates.
        $secondAttempt = $this->paymentService->initiate($order->fresh(), 'vnpay', $customer->id);
        $this->assertSame($payment->id, $secondAttempt->id);
        $this->assertSame($payment->txn_ref, $secondAttempt->txn_ref);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_signed_callback_transitions_pending_to_paid_and_updates_order(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->createOrderForCustomer($customer, ['grand_total' => 350000]);
        $payment = $this->paymentService->initiate($order, 'mock_gateway', $customer->id);

        $payload = $this->paymentService->signPayload((string) $payment->txn_ref, 350000, 'success');

        $response = $this->postJson(route('payments.callback'), $payload);

        $response->assertOk()
            ->assertJsonPath('already_processed', false)
            ->assertJsonPath('payment.txn_ref', $payment->txn_ref)
            ->assertJsonPath('payment.status', 'paid')
            ->assertJsonPath('payment.order_payment_status', 'paid');

        $payment->refresh();
        $order->refresh();

        $this->assertSame(PaymentStatus::PAID->value, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('mock_gateway', $order->payment_method);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment_callback_paid',
            'entity_type' => 'payment',
            'entity_id' => $payment->id,
        ]);
    }

    public function test_signed_callback_supports_pending_and_failed_states_and_allows_retry_with_new_txn_ref(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->createOrderForCustomer($customer, ['grand_total' => 280000]);
        $payment = $this->paymentService->initiate($order, 'vnpay', $customer->id);

        // 1. Callback pending -> keeps pending status
        $pendingPayload = $this->paymentService->signPayload((string) $payment->txn_ref, 280000, 'pending');
        $this->postJson(route('payments.callback'), $pendingPayload)
            ->assertOk()
            ->assertJsonPath('already_processed', true)
            ->assertJsonPath('payment.status', 'pending');

        $this->assertNull($payment->fresh()->paid_at);

        // 2. Callback failed -> marks payment & order failed
        $failedPayload = $this->paymentService->signPayload((string) $payment->txn_ref, 280000, 'failed');
        $this->postJson(route('payments.callback'), $failedPayload)
            ->assertOk()
            ->assertJsonPath('already_processed', false)
            ->assertJsonPath('payment.status', 'failed')
            ->assertJsonPath('payment.order_payment_status', 'failed');

        $this->assertSame('failed', $order->fresh()->payment_status);

        // 3. Re-initiating creates a brand new txn_ref which can later succeed
        $retryPayment = $this->paymentService->initiate($order->fresh(), 'momo', $customer->id);
        $this->assertNotSame($payment->id, $retryPayment->id);
        $this->assertNotSame($payment->txn_ref, $retryPayment->txn_ref);
        $this->assertStringStartsWith('MOMO-', (string) $retryPayment->txn_ref);

        $paidRetryPayload = $this->paymentService->signPayload((string) $retryPayment->txn_ref, 280000, 'paid');
        $this->postJson(route('payments.callback'), $paidRetryPayload)
            ->assertOk()
            ->assertJsonPath('payment.status', 'paid')
            ->assertJsonPath('payment.order_payment_status', 'paid');

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('momo', $order->fresh()->payment_method);
    }

    public function test_calling_callback_twice_with_same_txn_ref_is_strictly_idempotent(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->createOrderForCustomer($customer, ['grand_total' => 640000]);
        $payment = $this->paymentService->initiate($order, 'mock_gateway', $customer->id);

        $firstPayload = $this->paymentService->signPayload((string) $payment->txn_ref, 640000, 'paid');

        $this->postJson(route('payments.callback'), $firstPayload)
            ->assertOk()
            ->assertJsonPath('already_processed', false)
            ->assertJsonPath('payment.status', 'paid');

        $originalPaidAt = $payment->fresh()->paid_at;

        // Re-sending exact same signed callback
        $this->travel(5)->seconds();
        $this->postJson(route('payments.callback'), $firstPayload)
            ->assertOk()
            ->assertJsonPath('already_processed', true)
            ->assertJsonPath('payment.id', $payment->id)
            ->assertJsonPath('payment.status', 'paid');

        // Re-sending newly timestamped paid callback for same txn_ref
        $secondPayload = $this->paymentService->signPayload((string) $payment->txn_ref, 640000, 'paid');
        $this->postJson(route('payments.callback'), $secondPayload)
            ->assertOk()
            ->assertJsonPath('already_processed', true)
            ->assertJsonPath('payment.id', $payment->id);

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(
            $originalPaidAt?->toIso8601String(),
            $payment->fresh()->paid_at?->toIso8601String(),
        );
        $this->assertSame(
            1,
            AuditLog::query()
                ->where('entity_type', 'payment')
                ->where('entity_id', $payment->id)
                ->where('action', 'payment_callback_paid')
                ->count(),
        );
    }

    public function test_callback_with_invalid_tampered_missing_or_expired_signature_is_rejected_with_403(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->createOrderForCustomer($customer, ['grand_total' => 450000]);
        $payment = $this->paymentService->initiate($order, 'vnpay', $customer->id);

        $validPayload = $this->paymentService->signPayload((string) $payment->txn_ref, 450000, 'paid');

        // 1. Wrong signature
        $this->postJson(route('payments.callback'), [
            ...$validPayload,
            'signature' => str_repeat('a', 64),
        ])->assertForbidden()->assertJsonPath('error', 'invalid_signature');

        // 2. Missing signature
        $withoutSig = $validPayload;
        unset($withoutSig['signature']);
        $this->postJson(route('payments.callback'), $withoutSig)
            ->assertForbidden()
            ->assertJsonPath('error', 'invalid_signature');

        // 3. Tampered amount keeping old signature
        $this->postJson(route('payments.callback'), [
            ...$validPayload,
            'amount' => 1000,
        ])->assertForbidden()->assertJsonPath('error', 'invalid_signature');

        // 4. Tampered status keeping old signature
        $pendingSigned = $this->paymentService->signPayload((string) $payment->txn_ref, 450000, 'pending');
        $this->postJson(route('payments.callback'), [
            ...$pendingSigned,
            'status' => 'paid',
        ])->assertForbidden()->assertJsonPath('error', 'invalid_signature');

        // 5. Expired timestamp outside replay window
        $expiredPayload = $this->paymentService->signPayload(
            (string) $payment->txn_ref,
            450000,
            'paid',
            now()->subMinutes(20)->timestamp,
        );
        $this->postJson(route('payments.callback'), $expiredPayload)
            ->assertForbidden()
            ->assertJsonPath('error', 'invalid_signature');

        // 6. Webhook POST without Accept: application/json and empty body still returns 403 JSON (no 302 redirect)
        $this->post(route('payments.callback'), [])
            ->assertForbidden()
            ->assertJsonPath('error', 'invalid_signature');

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertNull($payment->fresh()->paid_at);
    }

    public function test_terminal_reversal_and_amount_mismatch_callbacks_are_rejected_safely(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->createOrderForCustomer($customer, ['grand_total' => 450000]);
        $payment = $this->paymentService->initiate($order, 'mock_gateway', $customer->id);

        // Signed payload with wrong amount fails server-side amount verification
        $wrongAmountSigned = $this->paymentService->signPayload((string) $payment->txn_ref, 100000, 'paid');
        $this->postJson(route('payments.callback'), $wrongAmountSigned)
            ->assertUnprocessable()
            ->assertJsonPath('error', 'payment_error');

        $this->assertSame('pending', $payment->fresh()->status);

        // Complete valid payment first
        $this->postJson(
            route('payments.callback'),
            $this->paymentService->signPayload((string) $payment->txn_ref, 450000, 'paid'),
        )->assertOk();

        // Attempt out-of-order failure callback after payment succeeded
        $this->postJson(
            route('payments.callback'),
            $this->paymentService->signPayload((string) $payment->txn_ref, 450000, 'failed'),
        )->assertUnprocessable()->assertJsonPath('error', 'payment_error');

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_cod_flow_remains_intact_and_rejects_gateway_callbacks(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $codOrder = $this->createOrderForCustomer($customer, [
            'order_code' => 'ORD-COD-100',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'grand_total' => 300000,
        ]);

        $codPayment = $this->paymentService->initiate($codOrder, 'cod', $customer->id);
        $this->assertSame('cod', $codPayment->method);
        $this->assertSame('COD-ORD-COD-100', $codPayment->txn_ref);
        $this->assertSame('pending', $codPayment->status);

        // Calling initiate('cod') again returns same COD payment idempotently
        $sameCod = $this->paymentService->initiate($codOrder->fresh(), 'cod', $customer->id);
        $this->assertSame($codPayment->id, $sameCod->id);

        // Even with a validly signed gateway payload, COD payments reject gateway callbacks
        $forgedCodCallback = $this->paymentService->signPayload((string) $codPayment->txn_ref, 300000, 'paid');
        $this->postJson(route('payments.callback'), $forgedCodCallback)
            ->assertUnprocessable()
            ->assertJsonPath('error', 'payment_error');

        $this->assertSame('pending', $codPayment->fresh()->status);
        $this->assertSame('pending', $codOrder->fresh()->payment_status);

        // Once COD order leaves pending state (e.g., confirmed/shipped), converting to online gateway is blocked
        $codOrder->update(['status' => 'confirmed']);
        $this->expectException(PaymentException::class);
        $this->paymentService->initiate($codOrder->fresh(), 'vnpay', $customer->id);
    }

    public function test_existing_seeder_cod_and_demo_gateway_orders_work_together(): void
    {
        $this->seed();
        app(OrderDemoSeeder::class)->run();

        $this->assertDatabaseHas('orders', [
            'order_code' => 'ORD-2026-1001',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
        ]);
        $this->assertDatabaseHas('orders', [
            'order_code' => 'ORD-2026-1003',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
        ]);
        $this->assertDatabaseHas('orders', [
            'order_code' => 'ORD-2026-1004',
            'payment_method' => 'vnpay',
            'payment_status' => 'pending',
        ]);
        $this->assertDatabaseHas('payments', [
            'txn_ref' => 'VNPAY-2026-1004',
            'method' => 'vnpay',
            'status' => 'pending',
        ]);
    }

    public function test_customer_simulator_ui_enforces_rbac_and_runs_interactive_simulation(): void
    {
        $customerA = User::factory()->create(['role' => 'customer']);
        $customerB = User::factory()->create(['role' => 'customer']);
        $vendor = User::factory()->create(['role' => 'vendor']);
        $admin = User::factory()->admin()->create();

        $orderA = $this->createOrderForCustomer($customerA, ['grand_total' => 450000]);

        // Guest & non-customer access blocked
        $this->get(route('customer.payments.simulator'))->assertRedirect(route('login'));
        $this->actingAs($vendor)->get(route('customer.payments.simulator'))->assertForbidden();
        $this->actingAs($admin)->get(route('customer.payments.simulator'))->assertForbidden();

        // Customer A initiates gateway session
        $this->actingAs($customerA)
            ->post(route('customer.payments.initiate', $orderA), ['method' => 'mock_gateway'])
            ->assertRedirect();

        $paymentA = Payment::query()->where('order_id', $orderA->id)->firstOrFail();

        // Customer B cannot view, initiate, or simulate Customer A's payment/order
        $this->actingAs($customerB)->post(route('customer.payments.initiate', $orderA), ['method' => 'vnpay'])->assertForbidden();
        $this->actingAs($customerB)->get(route('customer.payments.gateway', $paymentA))->assertForbidden();
        $this->actingAs($customerB)->post(route('customer.payments.simulate', $paymentA), ['outcome' => 'paid'])->assertForbidden();

        // Customer A runs tampered simulation -> error flash, DB remains pending
        $this->actingAs($customerA)
            ->from(route('customer.payments.gateway', $paymentA))
            ->post(route('customer.payments.simulate', $paymentA), ['outcome' => 'paid', 'tamper' => 1])
            ->assertRedirect(route('customer.payments.gateway', $paymentA))
            ->assertSessionHas('error');

        $this->assertSame('pending', $paymentA->fresh()->status);

        // Customer A clicks PAID twice -> succeeds idempotently
        $this->actingAs($customerA)
            ->post(route('customer.payments.simulate', $paymentA), ['outcome' => 'paid'])
            ->assertRedirect(route('customer.payments.gateway', $paymentA))
            ->assertSessionHas('success');

        $this->actingAs($customerA)
            ->post(route('customer.payments.simulate', $paymentA), ['outcome' => 'paid'])
            ->assertRedirect(route('customer.payments.gateway', $paymentA))
            ->assertSessionHas('success');

        $this->assertSame('paid', $paymentA->fresh()->status);
        $this->assertSame('paid', $orderA->fresh()->payment_status);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_verify_signature_throws_invalid_signature_exception_directly(): void
    {
        $this->expectException(InvalidPaymentSignatureException::class);

        $this->paymentService->verifySignature([
            'txn_ref' => 'PAY-123',
            'amount' => 100000,
            'status' => 'paid',
            'timestamp' => now()->timestamp,
            'signature' => 'invalid-signature',
        ]);
    }
}
