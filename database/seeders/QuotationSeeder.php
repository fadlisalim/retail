<?php

namespace Database\Seeders;

use App\Enums\QuotationStatus;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class QuotationSeeder extends Seeder
{
    public function run(): void
    {
        $customers = User::where('is_staff', false)->take(5)->get();
        $products = Product::inRandomOrder()->take(8)->get();

        $rows = [
            ['Pengadaan PLTS Atap Kantor', 'Jakarta', QuotationStatus::New, false],
            ['PLTS Off-Grid Tower BTS', 'Kalimantan Timur', QuotationStatus::UnderReview, false],
            ['Instalasi PJU Surya Desa', 'Jawa Barat', QuotationStatus::QuoteSent, true],
            ['Backup Listrik Pabrik', 'Jawa Timur', QuotationStatus::Approved, true],
            ['PLTS Rooftop Gudang', 'Banten', QuotationStatus::Converted, true],
        ];

        foreach ($rows as $i => [$project, $location, $status, $priced]) {
            $customer = $customers[$i % max(1, $customers->count())] ?? null;

            $quotation = Quotation::create([
                'rfq_number' => 'RFQ-'.now()->subDays(15 - $i)->format('ymd').'-'.strtoupper(Str::random(5)),
                'quotation_number' => $priced ? 'QUO-'.now()->format('ymd').'-'.strtoupper(Str::random(5)) : null,
                'public_token' => (string) Str::uuid(),
                'user_id' => $customer?->id,
                'status' => $status,
                'contact_name' => $customer?->name ?? 'Calon Pelanggan',
                'contact_email' => $customer?->email ?? 'proyek'.$i.'@example.test',
                'contact_phone' => '628120000'.$i,
                'company_name' => 'PT Proyek '.($i + 1),
                'project_name' => $project,
                'project_location' => $location,
                'needs_installation' => true,
                'technical_notes' => 'Mohon penawaran lengkap termasuk instalasi dan garansi.',
                'created_at' => now()->subDays(15 - $i),
            ]);

            $subtotal = 0;
            foreach ($products->random(min(3, $products->count())) as $product) {
                $qty = rand(2, 10);
                $unit = $priced ? $product->price : 0;
                $lineTotal = $unit * $qty;
                $subtotal += $lineTotal;
                $quotation->items()->create([
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'line_total' => $lineTotal,
                    'is_taxable' => true,
                ]);
            }

            if ($priced) {
                $tax = round($subtotal * 0.11);
                $quotation->update([
                    'items_subtotal' => $subtotal,
                    'shipping_cost' => 500000,
                    'tax_amount' => $tax,
                    'grand_total' => $subtotal + 500000 + $tax,
                    'payment_terms' => '50% DP, 50% sebelum pengiriman',
                    'valid_until' => now()->addDays(14),
                ]);
            }
        }
    }
}
