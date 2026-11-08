<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreIngredientRequest;
use App\Http\Requests\Admin\UpdateIngredientRequest;
use App\Models\Ingredient;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class IngredientController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Ingredient::class);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        $q = trim($data['q'] ?? '');
        $ingredients = Ingredient::with('aliases')->search($q)
            ->orderBy('display_name')->orderBy('id')->paginate(11)->withQueryString();

        return view('admin.ingredients.index', compact('ingredients', 'q'));
    }

    public function create()
    {
        Gate::authorize('create', Ingredient::class);

        return view('admin.ingredients.create');
    }

    public function store(StoreIngredientRequest $request)
    {
        $data = $request->validated();
        $data['function'] = $data['function'] ?? null;
        try {
            DB::transaction(function () use ($data): void {
                $ingredient = Ingredient::create(collect($data)->only(['inci_name', 'display_name', 'function'])->all());
                foreach ($data['aliases'] ?? [] as $alias) {
                    $ingredient->aliases()->create(['alias' => $alias]);
                }
            });
        } catch (QueryException $exception) {
            $this->rethrowConflict($exception);
        }

        return redirect()->route('admin.ingredients.index')->with('success', 'Đã tạo thành phần.');
    }

    public function edit(Ingredient $ingredient)
    {
        Gate::authorize('update', $ingredient);
        $ingredient->load('aliases');

        return view('admin.ingredients.edit', compact('ingredient'));
    }

    public function update(UpdateIngredientRequest $request, Ingredient $ingredient)
    {
        $data = $request->validated();
        $data['function'] = $data['function'] ?? null;
        try {
            DB::transaction(function () use ($ingredient, $data): void {
                $ingredient->update(collect($data)->only(['inci_name', 'display_name', 'function'])->all());
                $aliases = $data['aliases'] ?? [];
                $ingredient->aliases()->whereNotIn('alias', $aliases)->delete();
                foreach ($aliases as $alias) {
                    $ingredient->aliases()->firstOrCreate(['alias' => $alias]);
                }
            });
        } catch (QueryException $exception) {
            $this->rethrowConflict($exception);
        }

        return redirect()->route('admin.ingredients.edit', $ingredient)->with('success', 'Đã cập nhật thành phần.');
    }

    public function destroy(Ingredient $ingredient)
    {
        Gate::authorize('delete', $ingredient);
        if ($ingredient->products()->exists()) {
            return back()->with('error', 'Không thể xóa thành phần đang được sản phẩm sử dụng.');
        }
        try {
            DB::transaction(fn () => $ingredient->delete());
        } catch (QueryException $exception) {
            if (in_array((int) ($exception->errorInfo[1] ?? 0), [1451, 1452], true)) {
                return back()->with('error', 'Không thể xóa thành phần đang được sản phẩm sử dụng.');
            }
            throw $exception;
        }

        return redirect()->route('admin.ingredients.index')->with('success', 'Đã xóa thành phần.');
    }

    private function rethrowConflict(QueryException $exception): never
    {
        if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
            throw ValidationException::withMessages(['aliases' => 'Tên nguyên liệu hoặc alias đã được sử dụng.']);
        }
        throw $exception;
    }
}
