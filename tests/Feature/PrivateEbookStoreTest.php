<?php

namespace Tests\Feature;

use App\Models\DownloadPermission;
use App\Models\EbookRefund;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Commerce\EbookOrderFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateEbookStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\EnsureStudentDeviceIsApproved::class);
    }

    private function ebook(User $admin, array $overrides = []): Product
    {
        Storage::disk('local')->put('private/ebooks/guide.pdf', 'protected pdf bytes');

        return Product::create(array_merge([
            'type' => 'ebook',
            'name' => 'Grammar Guide',
            'slug' => 'grammar-guide',
            'regular_price' => 350,
            'currency' => 'BDT',
            'is_downloadable' => true,
            'download_path' => 'private/ebooks/guide.pdf',
            'download_limit' => 2,
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $admin->id,
        ], $overrides));
    }

    public function test_admin_uploads_ebook_pdf_to_private_storage(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.ebooks.store'), [
            'name' => 'IELTS Workbook',
            'regular_price' => '450.00',
            'currency' => 'BDT',
            'status' => 'published',
            'download_file' => UploadedFile::fake()->create('ielts.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $product = Product::where('slug', 'ielts-workbook')->firstOrFail();
        $this->assertStringStartsWith('private/ebooks/', $product->download_path);
        Storage::disk('local')->assertExists($product->download_path);
        $this->assertSame('published', $product->status);
    }

    public function test_manual_order_requires_admin_approval_before_private_download_and_enforces_limit(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $product = $this->ebook($admin);

        $this->actingAs($student)->post(route('ebooks.checkout', $product->slug), [
            'payment_reference' => 'EBOOK-PAY-001',
            'payment_sender' => '01700000000',
            'payment_proof' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'),
        ])->assertRedirect(route('ebooks.orders'));

        $order = Order::where('payment_reference', 'EBOOK-PAY-001')->firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertDatabaseCount('download_permissions', 0);
        Storage::disk('local')->assertExists($order->payment_proof_path);

        $permission = DownloadPermission::first();
        $this->actingAs($student)->get(route('ebooks.download', $permission ?? 999999))->assertNotFound();

        $this->actingAs($admin)->post(route('admin.ebooks.orders.approve', $order))->assertRedirect();
        $this->assertSame('completed', $order->fresh()->status);
        $permission = DownloadPermission::where('user_id', $student->id)->firstOrFail();
        $this->assertSame(2, $permission->downloads_remaining);

        $this->actingAs($student)->get(route('ebooks.download', $permission))->assertOk();
        $this->assertDatabaseHas('download_permissions', [
            'id' => $permission->id,
            'downloads_remaining' => 1,
            'download_count' => 1,
        ]);

        $this->actingAs($student)->get(route('ebooks.download', $permission))->assertOk();
        $this->assertDatabaseHas('download_permissions', [
            'id' => $permission->id,
            'downloads_remaining' => 0,
            'download_count' => 2,
        ]);
        $this->actingAs($student)->get(route('ebooks.download', $permission))->assertGone();
    }

    public function test_download_permission_cannot_be_used_by_another_student_or_after_expiry(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'student']);
        $other = User::factory()->create(['role' => 'student']);
        $product = $this->ebook($admin, ['download_expires_at' => now()->subMinute()]);
        $order = Order::create([
            'order_number' => 'AEC-TEST-EXPIRE-001',
            'user_id' => $owner->id,
            'status' => 'completed',
            'payment_method' => 'manual',
            'payment_reference' => 'EBOOK-EXPIRE-001',
            'currency' => 'BDT',
            'subtotal' => 350,
            'discount_total' => 0,
            'total' => 350,
            'paid_at' => now(),
            'completed_at' => now(),
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_type' => 'ebook',
            'product_name' => $product->name,
            'quantity' => 1,
            'regular_price' => 350,
            'line_total' => 350,
        ]);
        $permission = DownloadPermission::create([
            'order_item_id' => $item->id,
            'user_id' => $owner->id,
            'product_id' => $product->id,
            'downloads_remaining' => 2,
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($other)->get(route('ebooks.download', $permission))->assertNotFound();
        $this->actingAs($owner)->get(route('ebooks.download', $permission))->assertGone();
        $this->assertSame(0, $permission->fresh()->download_count);
    }

    public function test_rejected_order_never_grants_download_access(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $product = $this->ebook($admin);
        $order = Order::create([
            'order_number' => 'AEC-TEST-REJECT-001',
            'user_id' => $student->id,
            'status' => 'pending',
            'payment_method' => 'manual',
            'payment_reference' => 'EBOOK-REJECT-001',
            'currency' => 'BDT',
            'subtotal' => 350,
            'discount_total' => 0,
            'total' => 350,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_type' => 'ebook',
            'product_name' => $product->name,
            'quantity' => 1,
            'regular_price' => 350,
            'line_total' => 350,
        ]);

        $this->actingAs($admin)->post(route('admin.ebooks.orders.reject', $order))->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertDatabaseCount('download_permissions', 0);
        $this->assertSame('closed', app(EbookOrderFulfillmentService::class)->fulfill($order));
    }

    public function test_product_with_order_history_is_archived_instead_of_deleting_private_pdf(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $product = $this->ebook($admin);
        $order = Order::create([
            'order_number' => 'AEC-TEST-ARCHIVE-001',
            'user_id' => $student->id,
            'status' => 'pending',
            'payment_method' => 'manual',
            'payment_reference' => 'EBOOK-ARCHIVE-001',
            'currency' => 'BDT',
            'subtotal' => 350,
            'discount_total' => 0,
            'total' => 350,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_type' => 'ebook',
            'product_name' => $product->name,
            'quantity' => 1,
            'regular_price' => 350,
            'line_total' => 350,
        ]);

        $this->actingAs($admin)->delete(route('admin.ebooks.destroy', $product))->assertRedirect();
        $this->assertSame('archived', $product->fresh()->status);
        Storage::disk('local')->assertExists('private/ebooks/guide.pdf');
    }
    public function test_free_ebook_is_fulfilled_immediately_without_a_payment_reference(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $product = $this->ebook($admin, ['regular_price' => 0, 'download_limit' => null]);

        $this->actingAs($student)->post(route('ebooks.checkout', $product->slug), [])
            ->assertRedirect(route('ebooks.orders'));

        $order = Order::where('user_id', $student->id)->firstOrFail();
        $this->assertSame('completed', $order->status);
        $this->assertStringStartsWith('FREE-', $order->payment_reference);
        $permission = DownloadPermission::where('user_id', $student->id)->firstOrFail();
        $this->assertNull($permission->downloads_remaining);
        $this->actingAs($student)->get(route('ebooks.download', $permission))->assertOk();

        $this->actingAs($student)->post(route('ebooks.checkout', $product->slug), [])
            ->assertStatus(409);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('download_permissions', 1);
    }

    public function test_admin_cannot_replace_pdf_after_order_history_exists(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/ebooks/guide.pdf', 'original private pdf');
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $product = $this->ebook($admin);
        $order = Order::create([
            'order_number' => 'AEC-TEST-FILE-LOCK-001',
            'user_id' => $student->id,
            'status' => 'pending',
            'payment_method' => 'manual',
            'payment_reference' => 'EBOOK-FILE-LOCK-001',
            'currency' => 'BDT',
            'subtotal' => 350,
            'discount_total' => 0,
            'total' => 350,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_type' => 'ebook',
            'product_name' => $product->name,
            'quantity' => 1,
            'regular_price' => 350,
            'line_total' => 350,
        ]);

        $this->actingAs($admin)->from(route('admin.ebooks.index'))
            ->put(route('admin.ebooks.update', $product), [
                'name' => $product->name,
                'regular_price' => 350,
                'status' => 'published',
                'download_file' => UploadedFile::fake()->create('replacement.pdf', 12, 'application/pdf'),
            ])
            ->assertRedirect(route('admin.ebooks.index'))
            ->assertSessionHasErrors('download_file');

        $this->assertSame('private/ebooks/guide.pdf', $product->fresh()->download_path);
        Storage::disk('local')->assertExists('private/ebooks/guide.pdf');
    }

    public function test_admin_records_full_refund_revokes_access_and_sales_report_nets_the_refund(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $product = $this->ebook($admin);
        [$order, $item, $permission] = $this->completedEbookOrder($student, $product);

        $this->actingAs($admin)->post(route('admin.ebooks.orders.refund', $order), [
            'refund_reference' => 'REFUND-TRANSFER-001',
            'reason' => 'Customer requested a full refund.',
            'confirm_refund' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertDatabaseHas('ebook_refunds', [
            'order_id' => $order->id,
            'processed_by' => $admin->id,
            'amount' => '350.00',
            'refund_reference' => 'REFUND-TRANSFER-001',
        ]);
        $this->assertNotNull($permission->fresh()->revoked_at);
        $this->assertSame($admin->id, $permission->fresh()->revoked_by);
        $this->assertStringContainsString('Customer requested', $permission->fresh()->revocation_reason);

        $this->actingAs($student)->get(route('ebooks.download', $permission))->assertNotFound();

        $this->actingAs($admin)->get(route('admin.ebooks.sales'))
            ->assertOk()
            ->assertSee('350.00')
            ->assertSee('REFUND-TRANSFER-001');
        $refund = EbookRefund::firstOrFail();
        $this->assertSame('350.00', $refund->amount);
    }

    public function test_admin_can_revoke_access_without_refunding_and_cannot_refund_pending_orders(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $product = $this->ebook($admin);
        [$order, $item, $permission] = $this->completedEbookOrder($student, $product);

        $this->actingAs($admin)->post(route('admin.ebooks.permissions.revoke', $permission), [
            'reason' => 'Chargeback investigation pending.',
        ])->assertRedirect()->assertSessionHas('success');

        $permission->refresh();
        $this->assertNotNull($permission->revoked_at);
        $this->assertSame($admin->id, $permission->revoked_by);
        $this->actingAs($student)->get(route('ebooks.download', $permission))->assertGone();
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertDatabaseCount('ebook_refunds', 0);

        $pending = Order::create([
            'order_number' => 'AEC-TEST-PENDING-REFUND',
            'user_id' => $student->id,
            'status' => 'pending',
            'payment_method' => 'manual',
            'payment_reference' => 'PENDING-REFUND-REF',
            'currency' => 'BDT',
            'subtotal' => 350,
            'discount_total' => 0,
            'total' => 350,
        ]);
        $pending->items()->create([
            'product_id' => $product->id,
            'product_type' => 'ebook',
            'product_name' => $product->name,
            'quantity' => 1,
            'regular_price' => 350,
            'line_total' => 350,
        ]);

        $this->actingAs($admin)->from(route('admin.ebooks.orders.index'))
            ->post(route('admin.ebooks.orders.refund', $pending), [
                'refund_reference' => 'REFUND-PENDING-001',
                'reason' => 'Not eligible.',
                'confirm_refund' => '1',
            ])->assertRedirect()->assertSessionHas('error');
        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertDatabaseCount('ebook_refunds', 0);
    }

    public function test_ebook_refund_is_idempotent_and_requires_admin_authorization(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $product = $this->ebook($admin);
        [$order] = $this->completedEbookOrder($student, $product);

        $this->actingAs($student)->get(route('admin.ebooks.sales'))->assertForbidden();
        $this->post(route('admin.ebooks.orders.refund', $order), [
            'refund_reference' => 'STUDENT-REFUND-001',
            'reason' => 'Unauthorized.',
            'confirm_refund' => '1',
        ])->assertForbidden();

        $this->actingAs($admin)->post(route('admin.ebooks.orders.refund', $order), [
            'refund_reference' => 'REFUND-ONCE-001',
            'reason' => 'Full refund sent.',
            'confirm_refund' => '1',
        ])->assertRedirect();

        $this->post(route('admin.ebooks.orders.refund', $order), [
            'refund_reference' => 'REFUND-ONCE-002',
            'reason' => 'Duplicate request.',
            'confirm_refund' => '1',
        ])->assertRedirect()->assertSessionHas('info');

        $this->assertDatabaseCount('ebook_refunds', 1);
        $this->assertSame('refunded', $order->fresh()->status);
    }

    private function completedEbookOrder(User $student, Product $product): array
    {
        $order = Order::create([
            'order_number' => 'AEC-REFUND-'.strtoupper(bin2hex(random_bytes(4))),
            'user_id' => $student->id,
            'status' => 'completed',
            'payment_method' => 'manual',
            'payment_reference' => 'PAY-'.strtoupper(bin2hex(random_bytes(5))),
            'currency' => 'BDT',
            'subtotal' => 350,
            'discount_total' => 0,
            'total' => 350,
            'paid_at' => now(),
            'completed_at' => now(),
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_type' => 'ebook',
            'product_name' => $product->name,
            'quantity' => 1,
            'regular_price' => 350,
            'line_total' => 350,
        ]);
        $permission = DownloadPermission::create([
            'order_item_id' => $item->id,
            'user_id' => $student->id,
            'product_id' => $product->id,
            'downloads_remaining' => 2,
        ]);

        return [$order, $item, $permission];
    }

    public function test_admin_payment_proof_is_served_without_public_cache(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/ebook-payment-proofs/receipt.pdf', 'private receipt');
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $order = Order::create([
            'order_number' => 'AEC-TEST-PROOF-001',
            'user_id' => $student->id,
            'status' => 'pending',
            'payment_method' => 'manual',
            'payment_reference' => 'EBOOK-PROOF-001',
            'payment_proof_path' => 'private/ebook-payment-proofs/receipt.pdf',
            'currency' => 'BDT',
            'subtotal' => 350,
            'discount_total' => 0,
            'total' => 350,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.ebooks.orders.proof', $order))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

}
