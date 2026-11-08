<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProductSearchRequest;
use App\Services\Catalog\ProductSearchService;
use Illuminate\Http\JsonResponse;

final class ProductSearchController extends Controller
{
    public function __invoke(ProductSearchRequest $request, ProductSearchService $service): JsonResponse
    {
        return response()->json($service->search($request->validated()));
    }
}
