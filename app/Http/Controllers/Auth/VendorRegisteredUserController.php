<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VendorRegisterRequest;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class VendorRegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register-vendor');
    }

    public function store(VendorRegisterRequest $request): RedirectResponse
    {
        $licensePath = $request->file('license_file')->store('licenses', 'local');

        try {
            $user = DB::transaction(function () use ($request, $licensePath): User {
                $user = User::query()->create([
                    'name' => $request->string('name')->toString(),
                    'email' => $request->string('email')->toString(),
                    'phone' => $request->string('phone')->toString(),
                    'password' => $request->string('password')->toString(),
                    'role' => 'vendor',
                    'status' => 'active',
                ]);

                Vendor::query()->create([
                    'owner_user_id' => $user->id,
                    'store_name' => $request->string('store_name')->toString(),
                    'tax_code' => $request->string('tax_code')->toString(),
                    'phone' => $request->string('phone')->toString(),
                    'address' => $request->input('address'),
                    'license_file' => $licensePath,
                    'status' => 'pending',
                ]);

                return $user;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($licensePath);

            throw $e;
        }

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('vendor.pending')
            ->with('status', 'Hồ sơ đăng ký cửa hàng đã được gửi và đang chờ Admin xét duyệt.');
    }
}
