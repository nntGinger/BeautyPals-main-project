<?php

namespace App\Console\Commands;

use App\Models\Batch;
use Illuminate\Console\Command;

class ExpireBatchesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'batches:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Đánh dấu các lô hàng đã qua hạn sử dụng (exp_date < hôm nay) sang trạng thái expired';

    public function handle(): int
    {
        $today = now()->toDateString();

        $expiredCount = Batch::query()
            ->where('status', 'active')
            ->whereDate('exp_date', '<', $today)
            ->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);

        $this->info("Đã đánh dấu hết hạn {$expiredCount} lô hàng.");

        return self::SUCCESS;
    }
}
