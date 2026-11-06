<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngredientLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isApprovedVendor(), 403);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        $q = trim($data['q'] ?? '');
        if ($q === '') {
            return response()->json(['data' => []]);
        }

        return response()->json(['data' => Ingredient::query()->search($q)
            ->orderBy('display_name')->orderBy('id')->limit(20)
            ->get(['id', 'inci_name', 'display_name'])]);
    }
}
