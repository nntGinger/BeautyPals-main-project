<?php

namespace App\Console\Commands;

use App\Models\Batch;
use Illuminate\Console\Command;

class ExpireBatchesCommand extends Command
{
    protected $signature = 'batches:expire';

    protected $description = 'Đánh dấu các lô active hết hạn đến hôm nay thành expired';

    public function handle(): int
    {
        $count = Batch::query()->where('status', 'active')
            ->whereDate('exp_date', '<=', today('Asia/Ho_Chi_Minh')->toDateString())
            ->update(['status' => 'expired']);

        $this->info("Đã đánh dấu {$count} lô hết hạn.");

        return self::SUCCESS;
    }
}
