<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public const PASSWORD = 'Password123!';

    public function run(): void
    {
        // 1. Admin account (@beautypals.test for automated test suites, @example.com alias)
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
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => self::PASSWORD,
                'phone' => '0900000001',
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        // 2. Demo Customer 1 (Active)
        $customer = User::query()->updateOrCreate(
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

        $customerExample = User::query()->updateOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Customer Demo (Example)',
                'password' => self::PASSWORD,
                'phone' => '0900000002',
                'role' => 'customer',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        // Customer addresses
        Address::query()->updateOrCreate(
            ['user_id' => $customer->id, 'line' => '123 Nguyễn Huệ'],
            [
                'receiver' => 'Customer Demo',
                'phone' => '0900000002',
                'ward' => 'Phường Bến Nghé',
                'district' => 'Quận 1',
                'city' => 'TP. Hồ Chí Minh',
                'is_default' => true,
                'default_user_id' => $customer->id,
            ],
        );

        Address::query()->updateOrCreate(
            ['user_id' => $customer->id, 'line' => '45 Liễu Giai'],
            [
                'receiver' => 'Customer Demo (Văn phòng)',
                'phone' => '0900000002',
                'ward' => 'Phường Cống Vị',
                'district' => 'Quận Ba Đình',
                'city' => 'Hà Nội',
                'is_default' => false,
                'default_user_id' => null,
            ],
        );

        Address::query()->updateOrCreate(
            ['user_id' => $customerExample->id, 'line' => '123 Nguyễn Huệ'],
            [
                'receiver' => 'Customer Demo',
                'phone' => '0900000002',
                'ward' => 'Phường Bến Nghé',
                'district' => 'Quận 1',
                'city' => 'TP. Hồ Chí Minh',
                'is_default' => true,
                'default_user_id' => $customerExample->id,
            ],
        );

        // 3. Demo Customer 2 (Active)
        User::query()->updateOrCreate(
            ['email' => 'customer2@example.com'],
            [
                'name' => 'Nguyễn Văn An',
                'password' => self::PASSWORD,
                'phone' => '0900000006',
                'role' => 'customer',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        // 4. Locked Customer (For testing blocked access)
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

        // 5. Vendor 1 (Approved - BeautyPals Official Store)
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

        User::query()->updateOrCreate(
            ['email' => 'vendor@example.com'],
            [
                'name' => 'Vendor Demo (Example)',
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
                'address' => '1 Nguyễn Huệ, Phường Bến Nghé, Quận 1, TP.HCM',
                'license_file' => 'demo/licenses/vendor-approved.pdf',
                'status' => 'approved',
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ],
        );

        // 6. Vendor 2 (Approved - An Minh Cosmetics Store)
        $vendor2Owner = User::query()->updateOrCreate(
            ['email' => 'vendor2@example.com'],
            [
                'name' => 'Trần Thị Mai',
                'password' => self::PASSWORD,
                'phone' => '0900000007',
                'role' => 'vendor',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        Vendor::query()->updateOrCreate(
            ['owner_user_id' => $vendor2Owner->id],
            [
                'store_name' => 'An Minh Cosmetics Store',
                'tax_code' => '0309998888',
                'phone' => '0900000007',
                'address' => '78 Hai Bà Trưng, Phường Bến Nghé, Quận 1, TP.HCM',
                'license_file' => 'demo/licenses/vendor2-approved.pdf',
                'status' => 'approved',
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ],
        );

        // 6b. Vendor 3 (Approved - Saigon Derma Lab)
        $vendor3Owner = User::query()->updateOrCreate(
            ['email' => 'vendor3@beautypals.test'],
            [
                'name' => 'Phạm Quốc Bảo',
                'password' => self::PASSWORD,
                'phone' => '0900000009',
                'role' => 'vendor',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        Vendor::query()->updateOrCreate(
            ['owner_user_id' => $vendor3Owner->id],
            [
                'store_name' => 'Saigon Derma Lab',
                'tax_code' => '0318887777',
                'phone' => '0900000009',
                'address' => '150 Pasteur, Phường Bến Nghé, Quận 1, TP.HCM',
                'license_file' => 'demo/licenses/vendor3-approved.pdf',
                'status' => 'approved',
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ],
        );

        // 7. Vendor 3 (Pending - Pending Glow Shop)
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
                'address' => '2 Lê Lợi, Phường Bến Nghé, Quận 1, TP.HCM',
                'license_file' => 'demo/licenses/vendor-pending.pdf',
                'status' => 'pending',
                'approved_by' => null,
                'approved_at' => null,
            ],
        );

        // 8. Vendor 4 (Rejected - Fake Brand Store)
        $rejectedOwner = User::query()->updateOrCreate(
            ['email' => 'vendor.rejected@example.com'],
            [
                'name' => 'Lê Hoàng (Rejected)',
                'password' => self::PASSWORD,
                'phone' => '0900000008',
                'role' => 'vendor',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        Vendor::query()->updateOrCreate(
            ['owner_user_id' => $rejectedOwner->id],
            [
                'store_name' => 'Fake Brand Store',
                'tax_code' => '0109999999',
                'phone' => '0900000008',
                'address' => '999 Đường Tránh, Quận 12, TP.HCM',
                'license_file' => 'demo/licenses/vendor-rejected.pdf',
                'status' => 'rejected',
                'reject_reason' => 'Giấy phép kinh doanh và mã số thuế không hợp lệ.',
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ],
        );
    }
}
