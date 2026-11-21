<?php

namespace App\Services\Inventory;

use RuntimeException;

/**
 * Business error when inventory cannot be safely reserved, shipped or released.
 * Let the exception bubble up so the surrounding checkout/order transaction rolls back.
 */
class InventoryException extends RuntimeException {}
