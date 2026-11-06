<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidTransitionException;
use App\Services\OrderStateMachine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrderStateMachineTest extends TestCase
{
    private OrderStateMachine $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new OrderStateMachine;
    }

    public static function validTransitions(): array
    {
        return [
            'pending to confirmed' => [
                OrderStatus::PENDING,
                OrderStatus::CONFIRMED,
            ],
            'pending to cancelled' => [
                OrderStatus::PENDING,
                OrderStatus::CANCELLED,
            ],
            'pending to rejected' => [
                OrderStatus::PENDING,
                OrderStatus::REJECTED,
            ],
            'confirmed to packed' => [
                OrderStatus::CONFIRMED,
                OrderStatus::PACKED,
            ],
            'packed to shipped' => [
                OrderStatus::PACKED,
                OrderStatus::SHIPPED,
            ],
            'shipped to delivered' => [
                OrderStatus::SHIPPED,
                OrderStatus::DELIVERED,
            ],
            'delivered to completed' => [
                OrderStatus::DELIVERED,
                OrderStatus::COMPLETED,
            ],
        ];
    }

    #[DataProvider('validTransitions')]
    public function test_valid_transition(
        OrderStatus $from,
        OrderStatus $to
    ): void {
        $this->assertTrue(
            $this->service->canTransition($from, $to)
        );

        $this->service->assertCanTransition($from, $to);
    }

    public function test_pending_cannot_skip_to_delivered(): void
    {
        $this->assertFalse(
            $this->service->canTransition(
                OrderStatus::PENDING,
                OrderStatus::DELIVERED
            )
        );

        $this->expectException(
            InvalidTransitionException::class
        );

        $this->service->assertCanTransition(
            OrderStatus::PENDING,
            OrderStatus::DELIVERED
        );
    }

    public function test_cannot_transition_to_same_status(): void
    {
        foreach (OrderStatus::cases() as $status) {
            $this->assertFalse(
                $this->service->canTransition(
                    $status,
                    $status
                )
            );
        }
    }

    public function test_terminal_states_have_no_transitions(): void
    {
        $terminalStatuses = [
            OrderStatus::COMPLETED,
            OrderStatus::CANCELLED,
            OrderStatus::REJECTED,
        ];

        foreach ($terminalStatuses as $status) {
            $this->assertSame(
                [],
                $this->service->allowedTransitions($status)
            );
        }
    }

    public function test_invalid_transition_has_clear_message(): void
    {
        try {
            $this->service->assertCanTransition(
                OrderStatus::PENDING,
                OrderStatus::DELIVERED
            );

            $this->fail('Expected InvalidTransitionException.');
        } catch (InvalidTransitionException $exception) {
            $this->assertStringContainsString(
                'pending',
                $exception->getMessage()
            );

            $this->assertStringContainsString(
                'delivered',
                $exception->getMessage()
            );
        }
    }
}
