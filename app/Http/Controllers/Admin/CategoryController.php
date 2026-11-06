<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Category::class);

        $categories = Category::query()
            ->with('parent')
            ->withCount('products')
            ->orderByRaw('COALESCE(parent_id, id), parent_id IS NOT NULL, name')
            ->paginate(20);

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        Gate::authorize('create', Category::class);

        $parents = Category::query()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return view('admin.categories.create', compact('parents'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Category::class);

        $name = trim((string) $request->input('name', ''));
        $slug = trim((string) $request->input('slug', ''));
        if ($slug === '' && $name !== '') {
            $slug = Str::slug($name);
        }
        $request->merge(['name' => $name, 'slug' => $slug]);

        $validated = $request->validate(
            [
                'name' => ['required', 'string', 'max:120', 'unique:categories,name'],
                'slug' => ['required', 'string', 'max:150', 'unique:categories,slug'],
                'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            ],
            [
                'name.required' => 'Vui lòng nhập tên danh mục.',
                'name.unique' => 'Tên danh mục đã tồn tại.',
                'slug.unique' => 'Đường dẫn danh mục (slug) đã tồn tại.',
            ],
        );

        Category::query()->create($validated);

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Đã tạo danh mục thành công.');
    }

    public function edit(Category $category): View
    {
        Gate::authorize('update', $category);

        $parents = Category::query()
            ->whereNull('parent_id')
            ->whereKeyNot($category->id)
            ->orderBy('name')
            ->get();

        return view('admin.categories.edit', compact('category', 'parents'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        $name = trim((string) $request->input('name', ''));
        $slug = trim((string) $request->input('slug', ''));
        if ($slug === '' && $name !== '') {
            $slug = Str::slug($name);
        }
        $request->merge(['name' => $name, 'slug' => $slug]);

        $validated = $request->validate(
            [
                'name' => ['required', 'string', 'max:120', Rule::unique('categories', 'name')->ignore($category->id)],
                'slug' => ['required', 'string', 'max:150', Rule::unique('categories', 'slug')->ignore($category->id)],
                'parent_id' => [
                    'nullable',
                    'integer',
                    'exists:categories,id',
                    Rule::notIn([$category->id]),
                ],
            ],
            [
                'name.required' => 'Vui lòng nhập tên danh mục.',
                'name.unique' => 'Tên danh mục đã tồn tại.',
                'slug.unique' => 'Đường dẫn danh mục (slug) đã tồn tại.',
                'parent_id.not_in' => 'Danh mục không thể là danh mục cha của chính nó.',
            ],
        );

        $category->update($validated);

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Đã cập nhật danh mục thành công.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        if (
            $category->products()->exists()
            || $category->children()->exists()
            || $category->commissionRules()->exists()
        ) {
            return redirect()
                ->route('admin.categories.index')
                ->with('error', 'Không thể xóa danh mục đang có sản phẩm, danh mục con hoặc quy tắc hoa hồng.');
        }

        $category->delete();

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Đã xóa danh mục.');
    }
}
