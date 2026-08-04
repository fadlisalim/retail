<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderDocumentation;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Per-order documentation photos: admin upload, customer view, public gallery. */
class OrderDocumentationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'super-admin')->first());

        return $admin;
    }

    private function order(): Order
    {
        return Order::create([
            'order_number' => 'ORD-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(6)),
            'public_token' => \Illuminate\Support\Str::uuid(),
            'customer_name' => 'Ussy',
            'customer_email' => 'ussy@test.id',
            'status' => \App\Enums\OrderStatus::AwaitingPayment->value,
            'payment_status' => \App\Enums\PaymentStatus::Unpaid->value,
        ]);
    }

    public function test_admin_uploads_documentation_photos_for_an_order(): void
    {
        Storage::fake('public');
        $order = $this->order();

        $this->actingAs($this->admin())->post('/admin/pesanan/'.$order->public_token.'/dokumentasi', [
            'stage' => 'testing',
            'caption' => 'Testing inverter sebelum kirim',
            'is_public' => 1,
            'photos' => [UploadedFile::fake()->image('test1.jpg'), UploadedFile::fake()->image('test2.jpg')],
        ])->assertRedirect();

        $docs = $order->documentations()->get();
        $this->assertCount(2, $docs);
        $this->assertSame('testing', $docs[0]->stage);
        $this->assertSame('Testing inverter sebelum kirim', $docs[0]->caption);
        $this->assertTrue($docs[0]->is_public);
        $this->assertSame([1, 2], $docs->pluck('sort_order')->all());
        Storage::disk('public')->assertExists($docs[0]->path);
    }

    public function test_customer_sees_their_own_documentation_on_the_tracking_page(): void
    {
        $order = $this->order();
        $order->documentations()->create(['stage' => 'pengiriman', 'path' => 'dokumentasi/kirim.jpg', 'caption' => 'Serah terima ke kurir', 'is_public' => false]);

        $this->get('/pesanan/'.$order->public_token)
            ->assertOk()
            ->assertSee('Dokumentasi Pesanan')
            ->assertSee('Pengiriman')
            ->assertSee('Serah terima ke kurir');
    }

    public function test_public_gallery_shows_only_public_photos_and_never_customer_identity(): void
    {
        $order = $this->order();
        $order->documentations()->create(['stage' => 'persiapan', 'path' => 'dokumentasi/siap.jpg', 'caption' => 'Panel disiapkan', 'is_public' => true]);
        $order->documentations()->create(['stage' => 'packing', 'path' => 'dokumentasi/rahasia.jpg', 'caption' => 'Catatan privat', 'is_public' => false]);

        $response = $this->get('/dokumentasi')->assertOk();

        $response->assertSee('Panel disiapkan')
            ->assertDontSee('Catatan privat')
            ->assertDontSee('Ussy')                     // no customer name
            ->assertDontSee($order->order_number);      // no order number
    }

    public function test_public_gallery_can_be_filtered_by_stage(): void
    {
        $order = $this->order();
        $order->documentations()->create(['stage' => 'testing', 'path' => 'dokumentasi/a.jpg', 'caption' => 'Uji beban', 'is_public' => true]);
        $order->documentations()->create(['stage' => 'pengiriman', 'path' => 'dokumentasi/b.jpg', 'caption' => 'Muat ke truk', 'is_public' => true]);

        $this->get('/dokumentasi?tahap=testing')
            ->assertOk()
            ->assertSee('Uji beban')
            ->assertDontSee('Muat ke truk');
    }

    public function test_admin_can_toggle_visibility_and_delete_a_photo(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $order = $this->order();
        Storage::disk('public')->put('dokumentasi/x.jpg', 'x');
        $doc = $order->documentations()->create(['stage' => 'packing', 'path' => 'dokumentasi/x.jpg', 'is_public' => true]);

        $this->actingAs($admin)->post('/admin/pesanan/'.$order->public_token.'/dokumentasi/'.$doc->id.'/publik')->assertRedirect();
        $this->assertFalse($doc->fresh()->is_public);

        $this->actingAs($admin)->delete('/admin/pesanan/'.$order->public_token.'/dokumentasi/'.$doc->id)->assertRedirect();
        $this->assertSame(0, OrderDocumentation::count());
        Storage::disk('public')->assertMissing('dokumentasi/x.jpg');
    }

    public function test_photos_of_another_order_cannot_be_touched(): void
    {
        $admin = $this->admin();
        $orderA = $this->order();
        $orderB = $this->order();
        $doc = $orderB->documentations()->create(['stage' => 'packing', 'path' => 'dokumentasi/b.jpg']);

        $this->actingAs($admin)->delete('/admin/pesanan/'.$orderA->public_token.'/dokumentasi/'.$doc->id)->assertNotFound();
        $this->assertSame(1, OrderDocumentation::count());
    }

    public function test_upload_requires_permission(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['is_staff' => true, 'is_active' => true]); // no role
        $order = $this->order();

        $this->actingAs($user)->post('/admin/pesanan/'.$order->public_token.'/dokumentasi', [
            'stage' => 'testing',
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertForbidden();
    }
}
