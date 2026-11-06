<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VendorModerationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Vendor::class);

        $status = $request->string('status')->toString();
        $q = trim($request->string('q')->toString());

        $vendors = Vendor::query()
            ->with('owner')
            ->when(in_array($status, ['pending', 'approved', 'rejected', 'suspended'], true), fn ($query) => $query->where('status', $status))
            ->when($q !== '', function ($query) use ($q): void {
                $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q);
                $pattern = '%'.$escaped.'%';
                $query->where(function ($sub) use ($pattern): void {
                    $sub->whereRaw("store_name LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("tax_code LIKE ? ESCAPE '!'", [$pattern]);
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.vendors.index', compact('vendors', 'status', 'q'));
    }

    public function show(Vendor $vendor): View
    {
        Gate::authorize('view', $vendor);
        $vendor->load(['owner', 'approver'])->loadCount('products');

        return view('admin.vendors.show', compact('vendor'));
    }

    public function decide(Request $request, Vendor $vendor, string $decision): RedirectResponse
    {
        Gate::authorize('approve', $vendor);

        $reason = is_string($request->input('reason')) ? trim($request->input('reason')) : null;

        Validator::make(
            ['decision' => $decision, 'reason' => $reason],
            [
                'decision' => ['required', Rule::in(['approve', 'reject', 'suspend', 'restore'])],
                'reason' => [
                    Rule::requiredIf(in_array($decision, ['reject', 'suspend'], true)),
                    'nullable',
                    'string',
                    'max:255',
                ],
            ],
            [
                'reason.required' => 'Vui lòng nhập lý do từ chối hoặc tạm khóa cửa hàng.',
                'reason.max' => 'Lý do tối đa 255 ký tự.',
            ],
        )->validate();

        DB::transaction(function () use ($request, $vendor, $decision, $reason): void {
            $locked = Vendor::query()->whereKey($vendor->id)->lockForUpdate()->firstOrFail();

            $updates = match ($decision) {
                'approve', 'restore' => [
                    'status' => 'approved',
                    'reject_reason' => null,
                    'suspended_reason' => null,
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ],
                'reject' => [
                    'status' => 'rejected',
                    'reject_reason' => $reason,
                ],
                'suspend' => [
                    'status' => 'suspended',
                    'suspended_reason' => $reason,
                ],
            };

            $locked->update($updates);
        });

        $message = match ($decision) {
            'approve' => 'Đã duyệt cửa hàng thành công.',
            'restore' => 'Đã mở khóa cửa hàng thành công.',
            'reject' => 'Đã từ chối hồ sơ cửa hàng.',
            'suspend' => 'Đã tạm khóa cửa hàng.',
        };

        return redirect()
            ->route('admin.vendors.show', $vendor)
            ->with('status', $message);
    }

    public function license(Vendor $vendor): StreamedResponse
    {
        Gate::authorize('view', $vendor);

        abort_unless(
            $vendor->license_file && Storage::disk('local')->exists($vendor->license_file),
            404,
            'Không tìm thấy tệp giấy phép kinh doanh.',
        );

        return Storage::disk('local')->download($vendor->license_file);
    }
}
