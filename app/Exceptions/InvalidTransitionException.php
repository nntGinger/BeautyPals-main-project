<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use DomainException;

final class InvalidTransitionException extends DomainException
{
    public function __construct(
        OrderStatus $from,
        OrderStatus $to
    ) {
        parent::__construct(
            sprintf(
                'Cannot transition vendor order status from "%s" to "%s".',
                $from->value,
                $to->value
            )
        );
    }
}
