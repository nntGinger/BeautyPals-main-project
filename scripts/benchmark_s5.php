<?php

// Run only against the isolated MySQL benchmark database; never the web database.
use App\Models\Batch;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Services\Catalog\ProductSearchService;
use App\Support\InventoryDate;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'beautypals_s5_benchmark') {
    throw new RuntimeException('Benchmark requires MySQL database beautypals_s5_benchmark.');
}
Carbon::setTestNow(Carbon::parse('2026-11-23 12:00:00', 'UTC'));
$phase = $argv[1] ?? 'after';
if (! in_array($phase, ['before', 'after'], true)) {
    throw new RuntimeException('Phase must be before or after.');
}
if ($phase === 'before' && method_exists(InventoryDate::class, 'tomorrow')) {
    throw new RuntimeException('Capture before on baseline f3b0f8c, before applying S5-04 code/index changes.');
}
Artisan::call('migrate', ['--force' => true]);
if (DB::table('batches')->count() < 48000 || DB::table('vendor_orders')->count() < 12000) {
    $vendors = DB::table('vendors')->orderBy('id')->limit(20)->pluck('id')->all();
    for ($i = count($vendors); $i < 20; $i++) {
        $vendors[] = Vendor::factory()->approved()->create()->id;
    }
    $customer = User::factory()->customer()->create();
    $category = DB::table('categories')->where('slug', 's5-benchmark')->value('id')
        ?? DB::table('categories')->insertGetId(['name' => 'Benchmark', 'slug' => 's5-benchmark', 'created_at' => now(), 'updated_at' => now()]);
    $rows = [];
    for ($i = 1; $i <= 6000; $i++) {
        $rows[] = ['id' => $i, 'vendor_id' => $vendors[$i % 20], 'category_id' => $category, 'name' => 'Benchmark '.$i, 'slug' => 'bench-'.$i, 'status' => $i % 5 === 0 ? 'draft' : 'approved', 'created_at' => now()->subMinutes($i % 1000), 'updated_at' => now()];
    }
    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('products')->insertOrIgnore($chunk);
    }
    $rows = [];
    for ($i = 1; $i <= 12000; $i++) {
        $rows[] = ['id' => $i, 'product_id' => (int) ceil($i / 2), 'sku_code' => 'BENCH-'.$i, 'variant_name' => 'Demo', 'price' => 100000 + ($i % 10) * 50000, 'is_active' => $i % 7 !== 0, 'created_at' => now(), 'updated_at' => now()];
    }
    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('skus')->insertOrIgnore($chunk);
    }
    $rows = [];
    for ($i = 1; $i <= 12000; $i++) {
        for ($j = 0; $j < 4; $j++) {
            $rows[] = ['sku_id' => $i, 'batch_no' => 'BENCH-'.$j, 'mfg_date' => '2026-01-01', 'exp_date' => $j === 0 ? '2026-11-22' : '2027-01-'.str_pad((string) ($j + 1), 2, '0', STR_PAD_LEFT), 'qty_received' => 100, 'qty_on_hand' => 100, 'qty_reserved' => $j === 2 ? 100 : 5, 'status' => $j === 3 ? 'quarantined' : 'active', 'created_at' => now(), 'updated_at' => now()];
        }
    }
    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('batches')->insertOrIgnore($chunk);
    }
    $orders = [];
    $children = [];
    for ($i = 1; $i <= 12000; $i++) {
        $orders[] = ['id' => $i, 'order_code' => 'BENCH-ORD-'.$i, 'customer_id' => $customer->id, 'receiver' => 'Benchmark', 'phone' => '0900000000', 'address_text' => 'Benchmark only', 'subtotal' => 100000, 'grand_total' => 100000, 'idempotency_key' => (string) Str::uuid(), 'placed_at' => now(), 'created_at' => now(), 'updated_at' => now()];
        $children[] = ['order_id' => $i, 'vendor_id' => $vendors[$i % 20], 'vo_code' => 'BENCH-VO-'.$i, 'status' => intdiv($i, 20) % 3 === 0 ? 'pending' : 'completed', 'subtotal' => 100000, 'total' => 100000, 'created_at' => now()->subMinutes($i % 500), 'updated_at' => now()];
    }
    foreach (array_chunk($orders, 500) as $chunk) {
        DB::table('orders')->insertOrIgnore($chunk);
    }
    foreach (array_chunk($children, 500) as $chunk) {
        DB::table('vendor_orders')->insertOrIgnore($chunk);
    }
}
$vendorId = (int) DB::table('vendors')->min('id');
$cases = [
    'catalog_newest' => fn () => app(ProductSearchService::class)->search([]),
    'catalog_stock_price' => fn () => app(ProductSearchService::class)->search(['in_stock' => true, 'min_price' => 100000, 'max_price' => 400000]),
    'product_status_newest' => fn () => DB::table('products')->where('status', 'approved')->orderByDesc('created_at')->orderBy('id')->limit(15)->get(),
    'vendor_orders_pending' => fn () => VendorOrder::where('vendor_id', $vendorId)->where('status', 'pending')->orderByDesc('id')->paginate(15),
    'sku_active_prices' => fn () => DB::table('skus')->where('is_active', true)->select('product_id')->selectRaw('MIN(price) as price')->groupBy('product_id')->get(),
    'expired_batches' => fn () => method_exists(InventoryDate::class, 'tomorrow')
        ? Batch::where('status', 'active')->where('exp_date', '<', InventoryDate::tomorrow())->orderBy('exp_date')->orderBy('id')->limit(100)->get()
        : Batch::where('status', 'active')->whereDate('exp_date', '<=', InventoryDate::today())->orderBy('exp_date')->orderBy('id')->limit(100)->get(),
];
$report = ['phase' => $phase, 'mysql' => DB::selectOne('select version() as version')->version, 'catalog_source_sha256' => hash_file('sha256', __DIR__.'/../app/Services/Catalog/ProductSearchService.php'), 'rows' => []];
foreach (['products', 'skus', 'batches', 'vendor_orders'] as $table) {
    $report['rows'][$table] = DB::table($table)->count();
}
if ($report['rows'] !== ['products' => 6000, 'skus' => 12000, 'batches' => 48000, 'vendor_orders' => 12000]) {
    throw new RuntimeException('Benchmark dataset is incomplete or has unexpected records.');
}
foreach ($cases as $name => $run) {
    $run();
    $samples = [];
    for ($i = 0; $i < 5; $i++) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $start = hrtime(true);
        $result = $run();
        $samples[] = (hrtime(true) - $start) / 1e6;
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
    }
    sort($samples);
    $plans = [];
    foreach ($queries as $query) {
        if (str_contains($query['query'], 'information_schema') || str_contains($query['query'], 'sqlite_')) {
            continue;
        }
        if (str_starts_with(strtolower($query['query']), 'select')) {
            $plans[] = ['sql' => $query['query'], 'bindings' => $query['bindings'], 'explain' => DB::select('EXPLAIN '.$query['query'], $query['bindings'])];
        }
    }
    $items = method_exists($result, 'items') ? $result->items() : $result->all();
    $report['cases'][$name] = ['median_ms' => round($samples[2], 3), 'query_count' => count($queries), 'result_sha256' => hash('sha256', json_encode($items)), 'total' => method_exists($result, 'total') ? $result->total() : count($items), 'plans' => $plans];
}
$path = __DIR__.'/../.testing-local/s5-'.$phase.'.json';
if (! is_dir(dirname($path))) {
    mkdir(dirname($path), 0775, true);
}
file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode(['phase' => $phase, 'rows' => $report['rows'], 'cases' => array_map(fn ($case) => array_diff_key($case, ['plans' => true]), $report['cases'])], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
