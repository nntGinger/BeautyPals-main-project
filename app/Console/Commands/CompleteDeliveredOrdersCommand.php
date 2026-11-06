<?php

namespace App\Console\Commands;

use App\Services\OrderStatusService;
use Illuminate\Console\Command;

class CompleteDeliveredOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:complete {--days=7 : Số ngày sau khi giao thành công để tự động hoàn tất}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tự động chuyển các đơn vendor đã giao (delivered) quá số ngày quy định sang hoàn tất (completed)';

    public function handle(OrderStatusService $orderStatusService): int
    {
        $days = max(1, (int) $this->option('days'));
        $completedCount = $orderStatusService->autoCompleteDeliveredOrders($days);

        $this->info("Đã tự động hoàn tất {$completedCount} đơn hàng vendor.");

        return self::SUCCESS;
    }
}
