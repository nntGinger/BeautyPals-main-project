<?php

namespace App\Console\Commands;

use App\Services\Catalog\ProductSearchService;
use Illuminate\Console\Command;
use LogicException;

class CheckCatalogSchemaCommand extends Command
{
    protected $signature = 'catalog:check-schema';

    protected $description = 'Verify catalog tables and columns after deployment/migration';

    public function handle(ProductSearchService $service): int
    {
        try {
            $service->validateSchema();
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('Catalog schema is valid.');

        return self::SUCCESS;
    }
}
