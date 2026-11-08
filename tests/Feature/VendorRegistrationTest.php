<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VendorRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /* ------------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------------- */

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'                  => 'Nguyen Van A',
            'email'                 => 'newvendor@example.com',
            'phone'                 => '0912345678',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'store_name'            => 'My Beauty Shop',
            'tax_code'              => '1234567890',
            'address'               => '123 Đường ABC, Quận 1, HCM',
            'license_file'          => UploadedFile::fake()->create('license.pdf', 1024, 'application/pdf'),
        ], $overrides);
    }

    /* ------------------------------------------------------------------
     * Registration → pending, file on private disk, cannot access vendor area
     * ----------------------------------------------------------------- */

    public function test_vendor_registration_creates_pending_vendor_with_private_license_file(): void
    {
        $response = $this->post(route('vendor.register.store'), $this->validPayload());

        $response->assertRedirect(route('vendor.pending'));

        // User created with vendor role
        $user = User::where('email', 'newvendor@example.com')->firstOrFail();
        $this->assertSame('vendor', $user->role);

        // Vendor record with pending status
        $vendor = Vendor::where('owner_user_id', $user->id)->firstOrFail();
        $this->assertSame('pending', $vendor->status);

        // License file stored on private (local) disk
        $this->assertNotEmpty($vendor->license_file);
        Storage::disk('local')->assertExists($vendor->license_file);
    }

    public function test_pending_vendor_cannot_access_vendor_dashboard(): void
    {
        $vendor = Vendor::factory()->pending()->create();

        $this->actingAs($vendor->owner)->get('/vendor')
            ->assertRedirect(route('vendor.pending'));
    }

    public function test_pending_vendor_sees_status_page(): void
    {
        $vendor = Vendor::factory()->pending()->create();

        $this->actingAs($vendor->owner)->get(route('vendor.pending'))
            ->assertOk()
            ->assertSee('Hồ sơ đang chờ xét duyệt');
    }

    /* ------------------------------------------------------------------
     * File validation
     * ----------------------------------------------------------------- */

    public function test_registration_rejects_invalid_file_type(): void
    {
        $payload = $this->validPayload([
            'license_file' => UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream'),
        ]);

        $this->post(route('vendor.register.store'), $payload)
            ->assertSessionHasErrors('license_file');

        $this->assertDatabaseCount('vendors', 0);
    }

    public function test_registration_rejects_file_exceeding_5mb(): void
    {
        $payload = $this->validPayload([
            'license_file' => UploadedFile::fake()->create('big.pdf', 5121, 'application/pdf'),
        ]);

        $this->post(route('vendor.register.store'), $payload)
            ->assertSessionHasErrors('license_file');

        $this->assertDatabaseCount('vendors', 0);
    }

    public function test_registration_accepts_jpg_and_png(): void
    {
        $this->post(route('vendor.register.store'), $this->validPayload([
            'email'        => 'jpgvendor@example.com',
            'tax_code'     => '9999999991',
            'store_name'   => 'JPG Shop',
            'license_file' => UploadedFile::fake()->create('license.jpg', 1024, 'image/jpeg'),
        ]))->assertRedirect(route('vendor.pending'));

        $this->post(route('vendor.register.store'), $this->validPayload([
            'email'        => 'pngvendor@example.com',
            'tax_code'     => '9999999992',
            'store_name'   => 'PNG Shop',
            'license_file' => UploadedFile::fake()->create('license.png', 1024, 'image/png'),
        ]))->assertRedirect(route('vendor.pending'));
    }

    /* ------------------------------------------------------------------
     * License file is NOT publicly accessible
     * ----------------------------------------------------------------- */

    public function test_license_file_not_accessible_via_public_url(): void
    {
        $vendor = Vendor::factory()->pending()->create(['license_file' => 'licenses/secret.pdf']);

        // License file is stored strictly on private disk, not public disk
        Storage::disk('public')->assertMissing($vendor->license_file);

        // Public request without authorized signature is blocked (403/404)
        $response = $this->get('/storage/licenses/secret.pdf');
        $this->assertTrue(
            in_array($response->status(), [403, 404], true),
            "Expected 403 or 404, got {$response->status()}"
        );
    }

    public function test_admin_can_download_license_via_protected_route(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $vendor = Vendor::factory()->pending()->create();

        Storage::disk('local')->put($vendor->license_file, 'PDF content');

        $this->actingAs($admin)
            ->get(route('admin.vendors.license', $vendor))
            ->assertOk()
            ->assertHeader('Content-Disposition');
    }

    public function test_non_admin_cannot_download_license(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $vendor   = Vendor::factory()->pending()->create();

        $this->actingAs($customer)
            ->get(route('admin.vendors.license', $vendor))
            ->assertForbidden();
    }

    /* ------------------------------------------------------------------
     * Duplicate constraints
     * ----------------------------------------------------------------- */

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('vendor.register.store'), $this->validPayload([
            'email' => 'taken@example.com',
        ]))->assertSessionHasErrors('email');
    }

    public function test_registration_fails_with_duplicate_tax_code(): void
    {
        Vendor::factory()->create(['tax_code' => '1234567890']);

        $this->post(route('vendor.register.store'), $this->validPayload())
            ->assertSessionHasErrors('tax_code');
    }

    /* ------------------------------------------------------------------
     * Guest-only: logged-in user is redirected away from register form
     * ----------------------------------------------------------------- */

    public function test_authenticated_user_cannot_access_vendor_register_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('vendor.register.form'))
            ->assertRedirect();  // redirected by guest middleware
    }
}

