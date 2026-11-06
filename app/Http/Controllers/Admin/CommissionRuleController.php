<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CommissionRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommissionRuleController extends Controller
{
    public function index(): View
    {
        $rules = CommissionRule::query()
            ->with('category')
            ->orderByRaw('category_id IS NOT NULL')
            ->orderBy('category_id')
            ->orderByDesc('effective_from')
            ->paginate(20);

        $categories = Category::query()->orderBy('name')->get();

        return view('admin.commissions.index', compact('rules', 'categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $categoryKey = $categoryId ?? 0;

        $validated = $request->validate(
            [
                'category_id' => ['nullable', 'integer', 'exists:categories,id'],
                'rate' => ['required', 'numeric', 'min:0', 'max:100'],
                'effective_from' => [
                    'required',
                    'date',
                    Rule::unique('commission_rules', 'effective_from')->where(
                        fn ($q) => $q->where('category_key', $categoryKey),
                    ),
                ],
            ],
            [
                'rate.required' => 'Vui lòng nhập tỷ lệ hoa hồng.',
                'rate.min' => 'Tỷ lệ hoa hồng phải từ 0% đến 100%.',
                'rate.max' => 'Tỷ lệ hoa hồng phải từ 0% đến 100%.',
                'effective_from.required' => 'Vui lòng chọn ngày bắt đầu hiệu lực.',
                'effective_from.unique' => 'Quy tắc hoa hồng cho phạm vi này vào ngày hiệu lực đã tồn tại.',
            ],
        );

        CommissionRule::query()->create([
            'category_id' => $categoryId,
            'category_key' => $categoryKey,
            'rate' => number_format((float) $validated['rate'], 2, '.', ''),
            'effective_from' => $validated['effective_from'],
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.commissions.index')
            ->with('status', 'Đã thêm quy tắc hoa hồng mới.');
    }
}
