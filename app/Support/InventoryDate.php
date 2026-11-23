<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class InventoryDate
{
    public static function today(): string
    {
        return Carbon::today('Asia/Ho_Chi_Minh')->toDateString();
    }

    public static function tomorrow(): string
    {
        return Carbon::today('Asia/Ho_Chi_Minh')->addDay()->toDateString();
    }
}
