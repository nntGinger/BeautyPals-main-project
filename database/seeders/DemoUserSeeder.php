<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public const PASSWORD = 'Password123!';

    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@beautypals.test'],
            [
                'name' => 'Admin BeautyPals',
                'password' => self::PASSWORD,
                'phone' => '0900000001',
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'customer@beautypals.test'],
            [
                'name' => 'Customer Demo',
                'password' => self::PASSWORD,
                'phone' => '0900000002',
                'role' => 'customer',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        $vendorOwner = User::query()->updateOrCreate(
            ['email' => 'vendor@beautypals.test'],
            [
                'name' => 'Vendor Demo',
                'password' => self::PASSWORD,
                'phone' => '0900000003',
                'role' => 'vendor',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        Vendor::query()->updateOrCreate(
            ['owner_user_id' => $vendorOwner->id],
            [
                'store_name' => 'BeautyPals Official Store',
                'tax_code' => '0101234567',
                'phone' => '0900000003',
                'address' => '1 Nguyễn Huệ, Quận 1, TP.HCM',
                'license_file' => 'demo/licenses/vendor-approved.pdf',
                'status' => 'approved',
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'locked@beautypals.test'],
            [
                'name' => 'Locked Customer',
                'password' => self::PASSWORD,
                'phone' => '0900000004',
                'role' => 'customer',
                'status' => 'locked',
                'email_verified_at' => now(),
            ],
        );

        $pendingOwner = User::query()->updateOrCreate(
            ['email' => 'vendor.pending@beautypals.test'],
            [
                'name' => 'Vendor Pending',
                'password' => self::PASSWORD,
                'phone' => '0900000005',
                'role' => 'vendor',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        Vendor::query()->updateOrCreate(
            ['owner_user_id' => $pendingOwner->id],
            [
                'store_name' => 'Pending Glow Shop',
                'tax_code' => '0107654321',
                'phone' => '0900000005',
                'address' => '2 Lê Lợi, Quận 1, TP.HCM',
                'license_file' => 'demo/licenses/vendor-pending.pdf',
                'status' => 'pending',
                'approved_by' => null,
                'approved_at' => null,
            ],
        );
    }
}
