<?php

declare(strict_types=1);

use App\Http\Controllers\Catalog\ProductSearchController;
use Illuminate\Support\Facades\Route;

// UC04 guest catalogue. Do not use auth middleware; visibility is enforced in SQL.
Route::get('/api/catalog/products', ProductSearchController::class)
    ->middleware('throttle:60,1')
    ->name('catalog.uc04.search');
