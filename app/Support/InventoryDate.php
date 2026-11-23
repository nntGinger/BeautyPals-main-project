<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class InventoryDate
{
    public static function today(): string
    {
        return Carbon::today('Asia/Ho_Chi_Minh')->toDateString();
    }
}
