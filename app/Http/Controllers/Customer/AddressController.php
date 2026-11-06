<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Address::class);

        $addresses = Address::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return view('customer.addresses.index', compact('addresses'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Address::class);

        $validated = $request->validate(
            [
                'receiver' => ['required', 'string', 'max:120'],
                'phone' => ['required', 'string', 'max:20'],
                'line' => ['required', 'string', 'max:255'],
                'ward' => ['required', 'string', 'max:100'],
                'district' => ['required', 'string', 'max:100'],
                'city' => ['required', 'string', 'max:100'],
                'is_default' => ['nullable', 'boolean'],
            ],
            [
                'receiver.required' => 'Vui lòng nhập tên người nhận.',
                'phone.required' => 'Vui lòng nhập số điện thoại nhận hàng.',
                'line.required' => 'Vui lòng nhập địa chỉ số nhà, tên đường.',
                'ward.required' => 'Vui lòng nhập phường/xã.',
                'district.required' => 'Vui lòng nhập quận/huyện.',
                'city.required' => 'Vui lòng nhập tỉnh/thành phố.',
            ],
        );

        $userId = (int) $request->user()->id;

        DB::transaction(function () use ($userId, $validated, $request): void {
            $hasExisting = Address::query()->where('user_id', $userId)->exists();
            $makeDefault = $request->boolean('is_default') || ! $hasExisting;

            if ($makeDefault) {
                Address::query()
                    ->where('user_id', $userId)
                    ->where('is_default', true)
                    ->get()
                    ->each(fn (Address $addr) => $addr->update(['is_default' => false]));
            }

            Address::query()->create([
                'user_id' => $userId,
                'receiver' => trim($validated['receiver']),
                'phone' => trim($validated['phone']),
                'line' => trim($validated['line']),
                'ward' => trim($validated['ward']),
                'district' => trim($validated['district']),
                'city' => trim($validated['city']),
                'is_default' => $makeDefault,
            ]);
        });

        return redirect()
            ->route('customer.addresses.index')
            ->with('status', 'Đã thêm địa chỉ giao hàng mới.');
    }

    public function update(Request $request, Address $address): RedirectResponse
    {
        Gate::authorize('update', $address);

        $validated = $request->validate(
            [
                'receiver' => ['required', 'string', 'max:120'],
                'phone' => ['required', 'string', 'max:20'],
                'line' => ['required', 'string', 'max:255'],
                'ward' => ['required', 'string', 'max:100'],
                'district' => ['required', 'string', 'max:100'],
                'city' => ['required', 'string', 'max:100'],
                'is_default' => ['nullable', 'boolean'],
            ],
            [
                'receiver.required' => 'Vui lòng nhập tên người nhận.',
                'phone.required' => 'Vui lòng nhập số điện thoại nhận hàng.',
                'line.required' => 'Vui lòng nhập địa chỉ số nhà, tên đường.',
                'ward.required' => 'Vui lòng nhập phường/xã.',
                'district.required' => 'Vui lòng nhập quận/huyện.',
                'city.required' => 'Vui lòng nhập tỉnh/thành phố.',
            ],
        );

        DB::transaction(function () use ($address, $validated, $request): void {
            $makeDefault = $request->boolean('is_default') || $address->is_default;

            if ($makeDefault) {
                Address::query()
                    ->where('user_id', $address->user_id)
                    ->whereKeyNot($address->id)
                    ->where('is_default', true)
                    ->get()
                    ->each(fn (Address $addr) => $addr->update(['is_default' => false]));
            }

            $address->update([
                'receiver' => trim($validated['receiver']),
                'phone' => trim($validated['phone']),
                'line' => trim($validated['line']),
                'ward' => trim($validated['ward']),
                'district' => trim($validated['district']),
                'city' => trim($validated['city']),
                'is_default' => $makeDefault,
            ]);
        });

        return redirect()
            ->route('customer.addresses.index')
            ->with('status', 'Đã cập nhật địa chỉ giao hàng.');
    }

    public function setDefault(Address $address): RedirectResponse
    {
        Gate::authorize('update', $address);

        DB::transaction(function () use ($address): void {
            Address::query()
                ->where('user_id', $address->user_id)
                ->whereKeyNot($address->id)
                ->where('is_default', true)
                ->get()
                ->each(fn (Address $addr) => $addr->update(['is_default' => false]));

            $address->update(['is_default' => true]);
        });

        return redirect()
            ->route('customer.addresses.index')
            ->with('status', 'Đã đặt làm địa chỉ mặc định.');
    }

    public function destroy(Address $address): RedirectResponse
    {
        Gate::authorize('delete', $address);

        $wasDefault = $address->is_default;
        $userId = $address->user_id;

        DB::transaction(function () use ($address, $wasDefault, $userId): void {
            $address->delete();

            if ($wasDefault) {
                $next = Address::query()
                    ->where('user_id', $userId)
                    ->orderByDesc('id')
                    ->first();

                $next?->update(['is_default' => true]);
            }
        });

        return redirect()
            ->route('customer.addresses.index')
            ->with('status', 'Đã xóa địa chỉ giao hàng.');
    }
}
