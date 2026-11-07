<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\RegisterVendorRequest;
use App\Models\Vendor;
use App\Services\VendorStatusService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use App\Models\User;

class RegistrationController extends Controller
{
    public function create(): View
    {
        return view('vendor.register');
    }

    public function store(RegisterVendorRequest $request): RedirectResponse
    {
        $licenseFile = $request->file('license_file');
        $extension   = $licenseFile->getClientOriginalExtension();
        $storedPath  = 'licenses/' . Str::uuid() . '.' . $extension;

        // Store file on private disk BEFORE transaction (avoids holding tx open during I/O)
        Storage::disk('local')->put($storedPath, $licenseFile->get());

        try {
            DB::transaction(function () use ($request, $storedPath) {
                $user = User::create([
                    'name'     => $request->string('name')->toString(),
                    'email'    => $request->string('email')->toString(),
                    'phone'    => $request->input('phone'),
                    'password' => $request->string('password')->toString(),
                    'role'     => 'vendor',
                    'status'   => 'active',
                ]);

                Vendor::create([
                    'owner_user_id' => $user->id,
                    'store_name'    => $request->string('store_name')->toString(),
                    'tax_code'      => $request->string('tax_code')->toString(),
                    'phone'         => $request->input('phone'),
                    'address'       => $request->input('address'),
                    'license_file'  => $storedPath,
                    'status'        => 'pending',
                ]);

                event(new Registered($user));
                Auth::login($user);
                $request->session()->regenerate();
            });
        } catch (\Throwable $e) {
            // Clean up uploaded file if transaction failed
            Storage::disk('local')->delete($storedPath);
            throw $e;
        }

        return redirect()->route('vendor.pending');
    }

    /**
     * Vendor with status=rejected can resubmit to go back to pending.
     */
    public function resubmit(Request $request, VendorStatusService $service): RedirectResponse
    {
        $user   = $request->user();
        $vendor = $user->vendor;

        if (! $vendor || $vendor->status !== 'rejected') {
            return redirect()->route('vendor.pending')
                ->withErrors(['status' => 'Chỉ cửa hàng bị từ chối mới có thể nộp lại hồ sơ.']);
        }

        // Optionally replace license file
        if ($request->hasFile('license_file')) {
            $request->validate([
                'license_file' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ]);

            $old       = $vendor->license_file;
            $ext       = $request->file('license_file')->getClientOriginalExtension();
            $newPath   = 'licenses/' . Str::uuid() . '.' . $ext;
            Storage::disk('local')->put($newPath, $request->file('license_file')->get());
            $vendor->update(['license_file' => $newPath]);
            Storage::disk('local')->delete($old);
        }

        $service->transition($vendor, 'pending', $user);

        return redirect()->route('vendor.pending')
            ->with('success', 'Hồ sơ của bạn đã được nộp lại. Vui lòng chờ Admin xét duyệt.');
    }
}

