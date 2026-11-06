<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Brand::class);

        $brands = Brand::query()
            ->withCount('products')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.brands.index', compact('brands'));
    }

    public function create(): View
    {
        Gate::authorize('create', Brand::class);

        return view('admin.brands.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Brand::class);

        $request->merge(['name' => trim((string) $request->input('name', ''))]);

        $validated = $request->validate(
            [
                'name' => ['required', 'string', 'max:120', 'unique:brands,name'],
            ],
            [
                'name.required' => 'Vui lòng nhập tên thương hiệu.',
                'name.unique' => 'Tên thương hiệu đã tồn tại.',
            ],
        );

        Brand::query()->create($validated);

        return redirect()
            ->route('admin.brands.index')
            ->with('status', 'Đã thêm thương hiệu thành công.');
    }

    public function edit(Brand $brand): View
    {
        Gate::authorize('update', $brand);

        return view('admin.brands.edit', compact('brand'));
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        Gate::authorize('update', $brand);

        $request->merge(['name' => trim((string) $request->input('name', ''))]);

        $validated = $request->validate(
            [
                'name' => ['required', 'string', 'max:120', Rule::unique('brands', 'name')->ignore($brand->id)],
            ],
            [
                'name.required' => 'Vui lòng nhập tên thương hiệu.',
                'name.unique' => 'Tên thương hiệu đã tồn tại.',
            ],
        );

        $brand->update($validated);

        return redirect()
            ->route('admin.brands.index')
            ->with('status', 'Đã cập nhật thương hiệu.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        Gate::authorize('delete', $brand);

        if ($brand->products()->exists()) {
            return redirect()
                ->route('admin.brands.index')
                ->with('error', 'Không thể xóa thương hiệu đang có sản phẩm liên kết.');
        }

        $brand->delete();

        return redirect()
            ->route('admin.brands.index')
            ->with('status', 'Đã xóa thương hiệu.');
    }
}
